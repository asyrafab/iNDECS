<?php

namespace App\Models\Osc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaVersion extends Model
{
    protected $table = 'core.osc_agenda_versions';

    protected $guarded = [];

    /** Written with an explicit offset so timestamptz columns never depend on the database session timezone. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'changes' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}
