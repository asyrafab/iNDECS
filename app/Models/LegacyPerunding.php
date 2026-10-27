<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

final class LegacyPerunding extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'bangunan.legacy_perunding';

    protected $primaryKey = 'prd_id';

    protected $keyType = 'int';

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = static function (): never {
            throw new DomainException('Salinan warisan bangunan.legacy_* adalah baca sahaja.');
        };

        self::saving($guard);
        self::creating($guard);
        self::updating($guard);
        self::deleting($guard);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prd_id' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
