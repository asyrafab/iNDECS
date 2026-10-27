<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'auditable_type',
    'auditable_id',
    'event',
    'actor_id',
    'before_values',
    'after_values',
    'ip_address',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new DomainException('Rekod audit adalah tambah-sahaja dan tidak boleh diubah.');
        });

        static::deleting(function (): never {
            throw new DomainException('Rekod audit adalah tambah-sahaja dan tidak boleh dipadam.');
        });
    }

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
