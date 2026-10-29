<?php

namespace App\Models\Osc;

use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\TablingPurpose;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaItem extends Model
{
    use HasUlids;

    protected $table = 'core.osc_agenda_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AgendaItemStatus::class,
            'purpose' => TablingPurpose::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function papers(): HasMany
    {
        return $this->hasMany(AgendaItemPaper::class, 'agenda_item_id')->orderByDesc('version');
    }

    public function previousItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_item_id');
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }
}
