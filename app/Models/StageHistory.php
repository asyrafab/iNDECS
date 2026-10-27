<?php

namespace App\Models;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use DomainException;
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
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException('Rekod sejarah peringkat adalah tambah-sahaja dan tidak boleh diubah.');
        });

        static::deleting(function (): never {
            throw new DomainException('Rekod sejarah peringkat adalah tambah-sahaja dan tidak boleh dipadam.');
        });
    }

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
