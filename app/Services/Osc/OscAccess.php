<?php

declare(strict_types=1);

namespace App\Services\Osc;

use App\Enums\Osc\MeetingStatus;
use App\Models\Osc\Meeting;
use App\Models\User;

final class OscAccess
{
    public static function canManage(User $user): bool
    {
        return $user->canPerformOsc('osc.meeting.manage');
    }

    public static function isMember(User $user, Meeting $meeting): bool
    {
        return $meeting->members()->where('user_id', $user->id)->exists();
    }

    public static function canViewMeeting(User $user, Meeting $meeting): bool
    {
        if (self::canManage($user)) {
            return true;
        }
        if ($meeting->status !== MeetingStatus::Draft && self::isMember($user, $meeting)) {
            return true;
        }

        if ($meeting->items()->where(fn ($items) => $items->where('presenter_id', $user->id)->orWhere('proposed_by', $user->id))->exists()) {
            return true;
        }

        return self::endorsableDepartments($user, $meeting) !== [];
    }

    /** @return array<int, string> Departments in this meeting's items that the user may endorse for. */
    public static function endorsableDepartments(User $user, Meeting $meeting): array
    {
        return $meeting->items()->reorder()->distinct()->pluck('department_id')
            ->filter(fn ($department) => $user->canPerformOsc('osc.agenda.endorse', (string) $department))
            ->values()->all();
    }
}
