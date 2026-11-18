<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OfficerTaskAction extends Model
{
    use HasUlids;

    protected $table = 'core.officer_task_actions';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new DomainException('Sejarah tugasan adalah tambah-sahaja.');
        });
        self::deleting(function (): never {
            throw new DomainException('Sejarah tugasan adalah tambah-sahaja.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'evidence' => 'array', 'revision' => 'integer', 'created_at' => 'datetime'];
    }
}
