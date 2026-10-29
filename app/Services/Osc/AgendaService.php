<?php

declare(strict_types=1);

namespace App\Services\Osc;

use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\MeetingStatus;
use App\Enums\Osc\TablingPurpose;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\DepartmentSetting;
use App\Models\Osc\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AgendaService
{
    public function proposeLinked(Meeting $meeting, Application $application, TablingPurpose $purpose, User $presenter, User $actor): AgendaItem
    {
        $department = (string) $application->responsible_department_id;
        $this->authorisePropose($actor, $department);
        abort_unless($actor->canAccessApplication($application), 403);

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
        $department = (string) $actor->department_id;
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

    public function requiresEndorsement(string $departmentId): bool
    {
        return (bool) DepartmentSetting::query()->whereKey($departmentId)->value('requires_endorsement');
    }

    /** Managers may propose for any department; officers need the propose grant for the item's department. */
    private function authorisePropose(User $actor, string $department): void
    {
        abort_unless(OscAccess::canManage($actor) || $actor->canPerformOsc('osc.agenda.propose', $department), 403);
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
