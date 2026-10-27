<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LegacyPermohonan extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'bangunan.legacy_permohonan';

    protected $primaryKey = 'pmn_id';

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
            'pmn_id' => 'integer',
            'pmn_tkh_pohon' => 'date',
            'pmn_tkh_bayar' => 'date',
            'pmn_tkh_lulus' => 'date',
            'pmn_tkh_tolak' => 'date',
            'pmn_tkh_batal' => 'date',
            'pmn_tkh_tarik_balik' => 'date',
            'pmn_tkh_tangguh' => 'date',
            'pmn_bayaran' => 'decimal:2',
            'pmn_bayaran_cagaran' => 'decimal:2',
            'is_excluded_test_record' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function perunding(): HasMany
    {
        return $this->hasMany(LegacyPerunding::class, 'prd_pmn_no_siri', 'pmn_no_siri')
            ->orderBy('prd_id');
    }

    public function bangunan(): HasMany
    {
        return $this->hasMany(LegacyBangunan::class, 'bgn_pmn_no_siri', 'pmn_no_siri')
            ->orderBy('bgn_id');
    }

    public function ccc(): HasMany
    {
        return $this->hasMany(LegacyCcc::class, 'ccc_pmn_no_rujukan', 'pmn_no_rujukan')
            ->orderBy('sccc_id')
            ->orderBy('ccc_id');
    }

    /**
     * @return Collection<int, self>
     */
    public function siblingApplications(): Collection
    {
        return self::query()
            ->where('pmn_no_rujukan', $this->pmn_no_rujukan)
            ->where('pmn_id', '!=', $this->pmn_id)
            ->orderByDesc('pmn_tkh_pohon')
            ->orderByDesc('pmn_id')
            ->get();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->canAccessBangunanLegacy()) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('is_excluded_test_record', false);

        if ($user->role === 'system_admin' || blank($user->district)) {
            return $query;
        }

        return $query->where('district', $user->district);
    }

    /**
     * Evaluate effective status using the verified frmMain.fetch branch-date hierarchy
     * documented in 10-pemetaan-dan-migrasi.md §6.2.
     */
    public function effectiveStatusCode(): string
    {
        if ($this->pmn_tkh_tolak !== null) {
            return 'TOLAK';
        }

        if ($this->pmn_tkh_batal !== null) {
            return 'BATAL';
        }

        if ($this->pmn_tkh_tarik_balik !== null) {
            return 'TARIK_BALIK';
        }

        if ($this->pmn_tkh_lulus !== null) {
            return 'LULUS';
        }

        if ($this->pmn_tkh_tangguh !== null) {
            return 'TANGGUH';
        }

        return strtoupper(trim((string) ($this->pmn_status ?: 'B')));
    }

    public function effectiveStatusLabel(): string
    {
        return match ($this->effectiveStatusCode()) {
            'TOLAK' => 'Ditolak',
            'BATAL' => 'Dibatalkan',
            'TARIK_BALIK' => 'Ditarik Balik',
            'LULUS' => 'Pelan Lulus',
            'TANGGUH' => 'Ditangguhkan (Pindaan)',
            'B' => 'Dalam Proses (SKP2/SKP3)',
            'L' => 'Lawatan Tapak (SKP6)',
            'S' => 'Semakan Syarat (SKP7/SKP10a)',
            'BK' => 'Berif Kelulusan (SKP8)',
            'K' => 'Proses Kelulusan (SKP9/SKP10)',
            'T' => 'Pelan Lulus (T)',
            'CCC', 'CCC2A', 'CCC3', 'CCC4' => 'Fasa CCC',
            default => 'Dalam Proses ('.$this->effectiveStatusCode().')',
        };
    }

    public function statusBadgeTone(): string
    {
        return match ($this->effectiveStatusCode()) {
            'LULUS', 'T', 'CCC', 'CCC2A', 'CCC3', 'CCC4' => 'approved',
            'TOLAK', 'BATAL', 'TARIK_BALIK', 'TANGGUH' => 'returned_for_amendment',
            default => 'in_review',
        };
    }
}
