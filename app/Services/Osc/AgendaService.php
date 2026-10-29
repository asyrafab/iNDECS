<?php

declare(strict_types=1);

namespace App\Services\Osc;

use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\MeetingStatus;
use App\Enums\Osc\TablingPurpose;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\AgendaVersion;
use App\Models\Osc\DepartmentSetting;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Notifications\Osc\AgendaPublished;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class AgendaService
{
    public function proposeLinked(Meeting $meeting, Application $application, TablingPurpose $purpose, User $presenter, User $actor): AgendaItem
    {
        $department = (string) $application->responsible_department_id;
        $this->authorisePropose($actor, $department);
        abort_unless(OscAccess::canManage($actor) || $actor->canAccessApplication($application), 403);

        return DB::transaction(function () use ($meeting, $application, $purpose, $presenter, $actor, $department): AgendaItem {
            $record = $this->lockOpenMeeting($meeting, $actor);
            $this->assertPresenter($presenter->id);
            $previous = AgendaItem::query()->where('application_id', $application->id)
                ->orderByDesc('created_at')->orderByDesc('id')->first();

            return $this->create($record, $actor, [
                'application_id' => $application->id,
                'application_type_id' => $application->application_type_id,
                'department_id' => $department,
                'title' => $application->title,
                'reference' => $application->application_reference,
                'purpose' => $purpose,
                'presenter_id' => $presenter->id,
                'previous_item_id' => $previous?->id,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function proposeStandalone(Meeting $meeting, array $data, User $actor): AgendaItem
    {
        $department = $this->standaloneDepartment($data, $actor);
        $this->authorisePropose($actor, $department);

        return DB::transaction(function () use ($meeting, $data, $actor, $department): AgendaItem {
            $record = $this->lockOpenMeeting($meeting, $actor);
            $this->assertPresenter((int) ($data['presenter_id'] ?? 0));
            $purpose = $data['purpose'] instanceof TablingPurpose ? $data['purpose'] : TablingPurpose::from($data['purpose']);

            return $this->create($record, $actor, [
                'department_id' => $department,
                'standalone_type' => $data['standalone_type'],
                'title' => $data['title'],
                'reference' => $data['reference'] ?? null,
                'purpose' => $purpose,
                'presenter_id' => (int) $data['presenter_id'],
            ]);
        });
    }

    public function endorse(AgendaItem $item, int $expectedVersion, User $actor): AgendaItem
    {
        abort_unless($actor->canPerformOsc('osc.agenda.endorse', $item->department_id), 403);

        return DB::transaction(function () use ($item, $expectedVersion, $actor): AgendaItem {
            $this->assertAgendaEditable(Meeting::query()->lockForUpdate()->findOrFail($item->meeting_id));
            $record = AgendaItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($record->row_version !== $expectedVersion) {
                throw ValidationException::withMessages(['item' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
            }
            if ($record->status !== AgendaItemStatus::Proposed) {
                throw ValidationException::withMessages(['item' => 'Hanya cadangan yang belum disokong boleh disokong.']);
            }
            $record->update(['status' => AgendaItemStatus::Endorsed, 'row_version' => $record->row_version + 1]);
            $this->audit($record, 'osc_item_endorsed', $actor,
                ['status' => AgendaItemStatus::Proposed->value],
                ['status' => AgendaItemStatus::Endorsed->value]);

            return $record->refresh();
        });
    }

    public function accept(AgendaItem $item, int $expectedVersion, User $actor): AgendaItem
    {
        abort_unless(OscAccess::canManage($actor), 403);

        return DB::transaction(function () use ($item, $expectedVersion, $actor): AgendaItem {
            // The meeting lock serialises position allocation between concurrent accepts.
            $this->assertAgendaEditable(Meeting::query()->lockForUpdate()->findOrFail($item->meeting_id));
            $record = $this->lockItem($item, $expectedVersion);
            if ($record->status === AgendaItemStatus::Proposed && $this->requiresEndorsement($record->department_id)) {
                throw ValidationException::withMessages(['item' => 'Cadangan ini perlu disokong ketua jabatan terlebih dahulu.']);
            }
            if (! in_array($record->status, [AgendaItemStatus::Proposed, AgendaItemStatus::Endorsed], true)) {
                throw ValidationException::withMessages(['item' => 'Hanya cadangan yang belum diterima boleh diterima.']);
            }
            $position = (int) AgendaItem::query()->where('meeting_id', $record->meeting_id)
                ->where('status', AgendaItemStatus::Accepted->value)->max('position') + 1;
            $before = ['status' => $record->status->value, 'position' => $record->position];
            $record->update(['status' => AgendaItemStatus::Accepted, 'position' => $position,
                'return_reason' => null, 'row_version' => $record->row_version + 1]);
            $this->audit($record, 'osc_item_accepted', $actor, $before,
                ['status' => AgendaItemStatus::Accepted->value, 'position' => $position]);

            return $record->refresh();
        });
    }

    public function returnToDepartment(AgendaItem $item, int $expectedVersion, string $reason, User $actor): AgendaItem
    {
        abort_unless(OscAccess::canManage($actor), 403);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Sebab pengembalian diperlukan.']);
        }

        return DB::transaction(function () use ($item, $expectedVersion, $reason, $actor): AgendaItem {
            $this->assertAgendaEditable(Meeting::query()->lockForUpdate()->findOrFail($item->meeting_id));
            $record = $this->lockItem($item, $expectedVersion);
            if ($record->status === AgendaItemStatus::Returned) {
                throw ValidationException::withMessages(['item' => 'Cadangan ini telah dikembalikan.']);
            }
            $before = ['status' => $record->status->value, 'position' => $record->position];
            $record->update(['status' => AgendaItemStatus::Returned, 'position' => null,
                'return_reason' => $reason, 'row_version' => $record->row_version + 1]);
            $this->audit($record, 'osc_item_returned', $actor, $before,
                ['status' => AgendaItemStatus::Returned->value, 'position' => null, 'return_reason' => $reason]);

            return $record->refresh();
        });
    }

    /** @param array<int, string> $orderedItemIds */
    public function reorder(Meeting $meeting, array $orderedItemIds, int $expectedMeetingVersion, User $actor): Meeting
    {
        abort_unless(OscAccess::canManage($actor), 403);

        return DB::transaction(function () use ($meeting, $orderedItemIds, $expectedMeetingVersion, $actor): Meeting {
            $record = $this->lockMeeting($meeting, $expectedMeetingVersion);
            $current = $this->acceptedItems($record)->pluck('id')->all();
            $given = array_values($orderedItemIds);
            $sortedGiven = $given;
            $sortedCurrent = $current;
            sort($sortedGiven);
            sort($sortedCurrent);
            if ($sortedGiven !== $sortedCurrent) {
                throw ValidationException::withMessages(['items' => 'Susunan agenda tidak lengkap.']);
            }
            foreach ($given as $index => $id) {
                AgendaItem::query()->whereKey($id)->update(['position' => $index + 1]);
            }
            $record->update(['row_version' => $record->row_version + 1]);
            $this->auditMeeting($record, 'osc_agenda_reordered', $actor, ['order' => $current], ['order' => $given]);

            return $record->refresh();
        });
    }

    public function publish(Meeting $meeting, int $expectedMeetingVersion, User $actor): AgendaVersion
    {
        abort_unless(OscAccess::canManage($actor), 403);

        $version = DB::transaction(function () use ($meeting, $expectedMeetingVersion, $actor): AgendaVersion {
            $record = $this->lockMeeting($meeting, $expectedMeetingVersion);
            $accepted = $this->acceptedItems($record);
            if ($accepted->isEmpty()) {
                throw ValidationException::withMessages(['meeting' => 'Agenda tiada perkara untuk diterbitkan.']);
            }
            $items = $accepted->map(fn (AgendaItem $i): array => [
                'id' => $i->id, 'position' => $i->position, 'title' => $i->title, 'reference' => $i->reference,
                'purpose' => $i->purpose->value, 'department_id' => $i->department_id, 'presenter_id' => $i->presenter_id,
                'application_id' => $i->application_id, 'standalone_type' => $i->standalone_type,
            ])->all();
            $previous = AgendaVersion::query()->where('meeting_id', $record->id)->orderByDesc('version')->first();
            $number = $record->agenda_version + 1;
            $agenda = AgendaVersion::query()->create([
                'meeting_id' => $record->id, 'version' => $number, 'items' => $items,
                'changes' => $this->changes($previous->items ?? [], $items),
                'published_by' => $actor->id, 'published_at' => now(),
            ]);
            $before = ['status' => $record->status->value, 'agenda_version' => $record->agenda_version];
            $record->update([
                'agenda_version' => $number,
                'status' => $record->status === MeetingStatus::Draft ? MeetingStatus::AgendaPublished : $record->status,
                'row_version' => $record->row_version + 1,
            ]);
            $this->auditMeeting($record, 'osc_agenda_published', $actor, $before,
                ['status' => $record->status->value, 'agenda_version' => $number, 'item_count' => count($items)]);

            return $agenda;
        });

        $record = Meeting::query()->findOrFail($version->meeting_id);
        $recipients = User::query()
            ->whereIn('id', $record->members()->pluck('user_id'))
            ->where('id', '!=', $actor->id)->get();
        Notification::send($recipients, new AgendaPublished($record, count($version->items), $version->version));

        return $version;
    }

    public function requiresEndorsement(string $departmentId): bool
    {
        return (bool) DepartmentSetting::query()->whereKey($departmentId)->value('requires_endorsement');
    }

    /** Managers may propose for any department; officers need the propose grant for the item's department. */
    private function authorisePropose(User $actor, string $department): void
    {
        abort_unless(OscAccess::canManage($actor) || $actor->canPerformOsc('osc.agenda.propose', $department), 403);
    }

    /** @param array<string, mixed> $data */
    private function standaloneDepartment(array $data, User $actor): string
    {
        if (! OscAccess::canManage($actor)) {
            return (string) $actor->department_id;
        }
        $department = (string) ($data['department_id'] ?? '');
        if ($department === '' || ! DB::table('departments')->where('id', $department)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Jabatan mesti dipilih.']);
        }

        return $department;
    }

    private function lockOpenMeeting(Meeting $meeting, User $actor): Meeting
    {
        $record = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
        if (in_array($record->status, [MeetingStatus::InProgress, MeetingStatus::Closed, MeetingStatus::MinutesConfirmed], true)) {
            throw ValidationException::withMessages(['meeting' => 'Mesyuarat ini tidak lagi menerima cadangan.']);
        }
        if ($record->proposal_cutoff_at->isPast() && ! OscAccess::canManage($actor)) {
            throw ValidationException::withMessages(['meeting' => 'Tarikh tutup cadangan agenda telah berlalu.']);
        }

        return $record;
    }

    private function assertPresenter(int $presenterId): void
    {
        $valid = User::query()->whereKey($presenterId)->where('is_active', true)->where('account_type', 'staff')->exists();
        if (! $valid) {
            throw ValidationException::withMessages(['presenter_id' => 'Penyampai mesti kakitangan yang aktif.']);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function create(Meeting $meeting, User $actor, array $attributes): AgendaItem
    {
        $item = $meeting->items()->create($attributes + [
            'proposed_by' => $actor->id,
            'status' => AgendaItemStatus::Proposed,
        ]);
        $this->audit($item, 'osc_item_proposed', $actor, null, [
            'meeting_id' => $meeting->id,
            'application_id' => $item->application_id,
            'department_id' => $item->department_id,
            'title' => $item->title,
            'purpose' => $item->purpose->value,
            'previous_item_id' => $item->previous_item_id,
        ]);

        return $item->refresh();
    }

    private function lockItem(AgendaItem $item, int $expectedVersion): AgendaItem
    {
        $record = AgendaItem::query()->lockForUpdate()->findOrFail($item->id);
        if ($record->row_version !== $expectedVersion) {
            throw ValidationException::withMessages(['item' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
        }

        return $record;
    }

    private function lockMeeting(Meeting $meeting, int $expectedVersion): Meeting
    {
        $record = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
        if ($record->row_version !== $expectedVersion) {
            throw ValidationException::withMessages(['meeting' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
        }
        $this->assertAgendaEditable($record);

        return $record;
    }

    private function assertAgendaEditable(Meeting $locked): void
    {
        if (in_array($locked->status, [MeetingStatus::Closed, MeetingStatus::MinutesConfirmed], true)) {
            throw ValidationException::withMessages(['meeting' => 'Agenda mesyuarat ini tidak lagi boleh diubah.']);
        }
    }

    /** @return Collection<int, AgendaItem> */
    private function acceptedItems(Meeting $meeting): Collection
    {
        return AgendaItem::query()->where('meeting_id', $meeting->id)
            ->where('status', AgendaItemStatus::Accepted->value)->orderBy('position')->orderBy('id')->get();
    }

    /**
     * Relative order is compared only over ids present in both versions.
     *
     * @param  array<int, array<string, mixed>>  $previous
     * @param  array<int, array<string, mixed>>  $current
     * @return array{added: array<int, string>, removed: array<int, string>, reordered: bool}
     */
    private function changes(array $previous, array $current): array
    {
        $old = array_column($previous, 'id');
        $new = array_column($current, 'id');

        return [
            'added' => array_values(array_diff($new, $old)),
            'removed' => array_values(array_diff($old, $new)),
            'reordered' => array_values(array_intersect($old, $new)) !== array_values(array_intersect($new, $old)),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function auditMeeting(Meeting $meeting, string $event, User $actor, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'auditable_type' => Meeting::class, 'auditable_id' => $meeting->id, 'event' => $event,
            'actor_id' => $actor->id, 'before_values' => $before, 'after_values' => $after,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(AgendaItem $item, string $event, User $actor, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'auditable_type' => AgendaItem::class, 'auditable_id' => $item->id, 'event' => $event,
            'actor_id' => $actor->id, 'before_values' => $before, 'after_values' => $after,
            'ip_address' => request()?->ip(),
        ]);
    }
}
