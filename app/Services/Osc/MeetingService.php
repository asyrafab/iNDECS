<?php

declare(strict_types=1);

namespace App\Services\Osc;

use App\Enums\Osc\MeetingRole;
use App\Enums\Osc\MeetingStatus;
use App\Models\AuditLog;
use App\Models\Osc\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MeetingService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Meeting
    {
        abort_unless(OscAccess::canManage($actor), 403);

        return DB::transaction(function () use ($data, $actor): Meeting {
            $meeting = Meeting::query()->create([
                'number' => $data['number'],
                'scheduled_at' => $data['scheduled_at'],
                'venue' => $data['venue'],
                'proposal_cutoff_at' => $data['proposal_cutoff_at'],
                'paper_cutoff_at' => $data['paper_cutoff_at'],
                'status' => MeetingStatus::Draft,
                'created_by' => $actor->id,
            ]);
            $this->writeMembers($meeting, $data);
            $after = $this->memberList($meeting);
            $this->audit($meeting, 'osc_meeting_created', $actor, null, [
                'number' => $meeting->number, 'status' => MeetingStatus::Draft->value, 'members' => $after,
            ]);

            return $meeting->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function setMembers(Meeting $meeting, array $data, int $expectedVersion, User $actor): Meeting
    {
        abort_unless(OscAccess::canManage($actor), 403);

        return DB::transaction(function () use ($meeting, $data, $expectedVersion, $actor): Meeting {
            $record = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            if ($record->row_version !== $expectedVersion) {
                throw ValidationException::withMessages(['meeting' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
            }
            if (in_array($record->status, [MeetingStatus::Closed, MeetingStatus::MinutesConfirmed], true)) {
                throw ValidationException::withMessages(['meeting' => 'Ahli mesyuarat tidak boleh diubah selepas mesyuarat selesai.']);
            }
            $before = $this->memberList($record);
            $record->members()->delete();
            $this->writeMembers($record, $data);
            $record->update(['row_version' => $record->row_version + 1]);
            $this->audit($record, 'osc_meeting_members_changed', $actor, ['members' => $before], ['members' => $this->memberList($record)]);

            return $record->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function writeMembers(Meeting $meeting, array $data): void
    {
        $rows = [[$data['chair_id'], MeetingRole::Chair], [$data['secretary_id'], MeetingRole::Secretary]];
        if (! empty($data['steno_id'])) {
            $rows[] = [$data['steno_id'], MeetingRole::Steno];
        }
        $taken = array_map(fn (array $row): int => (int) $row[0], $rows);
        foreach (array_unique(array_map('intval', $data['member_ids'] ?? [])) as $id) {
            if (! in_array($id, $taken, true)) {
                $rows[] = [$id, MeetingRole::Member];
            }
        }
        foreach ($rows as [$userId, $role]) {
            $meeting->members()->create(['user_id' => $userId, 'role' => $role]);
        }
    }

    /** @return array<int, array{user_id: int, role: string}> */
    private function memberList(Meeting $meeting): array
    {
        return $meeting->members()->orderBy('id')->get()
            ->map(fn ($m): array => ['user_id' => (int) $m->user_id, 'role' => $m->role->value])->all();
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(Meeting $meeting, string $event, User $actor, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'auditable_type' => Meeting::class, 'auditable_id' => $meeting->id, 'event' => $event,
            'actor_id' => $actor->id, 'before_values' => $before, 'after_values' => $after,
            'ip_address' => request()?->ip(),
        ]);
    }
}
