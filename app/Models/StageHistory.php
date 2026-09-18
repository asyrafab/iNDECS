<?php

namespace App\Models;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id',
    'previous_stage',
    'new_stage',
    'previous_status',
    'new_status',
    'action',
    'actor_id',
    'remarks',
])]
class StageHistory extends Model
{
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return [
            'previous_stage' => ApplicationStage::class,
            'new_stage' => ApplicationStage::class,
            'previous_status' => ApplicationStatus::class,
            'new_status' => ApplicationStatus::class,
        ];
    }
}
