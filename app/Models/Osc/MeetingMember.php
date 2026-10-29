<?php

namespace App\Models\Osc;

use App\Enums\Osc\MeetingRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMember extends Model
{
    protected $table = 'core.osc_meeting_members';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['role' => MeetingRole::class];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
