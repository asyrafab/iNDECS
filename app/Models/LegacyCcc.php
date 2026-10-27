<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

final class LegacyCcc extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'bangunan.legacy_ccc';

    protected $primaryKey = 'ccc_id';

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
            'ccc_id' => 'integer',
            'sccc_id' => 'integer',
            'ccc_tkh_ccc' => 'date',
            'synced_at' => 'datetime',
        ];
    }
}
