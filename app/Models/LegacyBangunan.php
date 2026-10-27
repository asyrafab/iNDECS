<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

final class LegacyBangunan extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'bangunan.legacy_bangunan';

    protected $primaryKey = 'bgn_id';

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
            'bgn_id' => 'integer',
            'bgn_unit' => 'integer',
            'bgn_tingkat' => 'integer',
            'bgn_luas' => 'decimal:2',
            'bgn_jumlah' => 'decimal:2',
            'bgn_chk_spah' => 'boolean',
            'bgn_chk_ottv' => 'boolean',
            'bgn_chk_oku' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
