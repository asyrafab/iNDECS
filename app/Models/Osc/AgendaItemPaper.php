<?php

namespace App\Models\Osc;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaItemPaper extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'core.osc_agenda_item_papers';

    protected $guarded = [];

    public function item(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'agenda_item_id');
    }
}
