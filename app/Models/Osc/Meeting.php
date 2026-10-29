<?php

namespace App\Models\Osc;

use App\Enums\Osc\MeetingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    use HasUlids;

    protected $table = 'core.osc_meetings';

    protected $guarded = [];

    /** Written with an explicit offset so timestamptz columns never depend on the database session timezone. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'scheduled_at' => 'datetime',
            'proposal_cutoff_at' => 'datetime',
            'paper_cutoff_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(MeetingMember::class, 'meeting_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AgendaItem::class, 'meeting_id')
            ->orderByRaw('position asc nulls last')
            ->orderBy('created_at');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgendaVersion::class, 'meeting_id')->orderBy('version');
    }
}
