<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegacyPermohonan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class BangunanLegacyController extends Controller
{
    private const DISTRICTS = [
        'Seberang Perai Utara',
        'Seberang Perai Tengah',
        'Seberang Perai Selatan',
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_FILTERS = [
        'LULUS' => 'Pelan Lulus',
        'DALAM_PROSES' => 'Dalam Proses (B/L/S/BK/K)',
        'TANGGUH' => 'Ditangguhkan (Pindaan)',
        'TOLAK' => 'Ditolak / Batal / Tarik Balik',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $selectedDistrict = trim($request->string('district')->toString());
        $selectedStatus = trim($request->string('status')->toString());

        $base = LegacyPermohonan::query()->visibleTo($user);

        $metrics = [
            'total' => (clone $base)->count(),
            'approved' => (clone $base)
                ->whereNull('pmn_tkh_tolak')
                ->whereNull('pmn_tkh_batal')
                ->whereNull('pmn_tkh_tarik_balik')
                ->where(function (Builder $query): void {
                    $query->whereNotNull('pmn_tkh_lulus')
                        ->orWhereIn('pmn_status', ['T', 'CCC', 'CCC2a', 'CCC3', 'CCC4']);
                })
                ->count(),
            'in_process' => (clone $base)
                ->whereNull('pmn_tkh_tolak')
                ->whereNull('pmn_tkh_batal')
                ->whereNull('pmn_tkh_tarik_balik')
                ->whereNull('pmn_tkh_lulus')
                ->whereNull('pmn_tkh_tangguh')
                ->count(),
            'deferred_or_closed' => (clone $base)
                ->where(function (Builder $query): void {
                    $query->whereNotNull('pmn_tkh_tangguh')
                        ->orWhereNotNull('pmn_tkh_tolak')
                        ->orWhereNotNull('pmn_tkh_batal')
                        ->orWhereNotNull('pmn_tkh_tarik_balik');
                })
                ->count(),
        ];

        $records = LegacyPermohonan::query()
            ->visibleTo($user)
            ->withCount(['perunding', 'bangunan'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('pmn_no_rujukan', 'ilike', "%{$search}%")
                        ->orWhere('pmn_no_siri', 'ilike', "%{$search}%")
                        ->orWhere('pmn_tajuk', 'ilike', "%{$search}%")
                        ->orWhere('pmn_pemohon', 'ilike', "%{$search}%")
                        ->orWhere('pmn_no_lot', 'ilike', "%{$search}%")
                        ->orWhere('pmn_tempat', 'ilike', "%{$search}%");
                });
            })
            ->when(
                in_array($selectedDistrict, self::DISTRICTS, true),
                fn (Builder $query) => $query->where('district', $selectedDistrict),
            )
            ->when(
                array_key_exists($selectedStatus, self::STATUS_FILTERS),
                fn (Builder $query) => $this->applyStatusFilter($query, $selectedStatus),
            )
            ->orderByDesc('pmn_tkh_pohon')
            ->orderByDesc('pmn_id')
            ->paginate(15)
            ->withQueryString();

        return view('bangunan.legacy.index', [
            'records' => $records,
            'metrics' => $metrics,
            'districts' => self::DISTRICTS,
            'statusFilters' => self::STATUS_FILTERS,
            'search' => $search,
            'selectedDistrict' => $selectedDistrict,
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function show(Request $request, LegacyPermohonan $legacyPermohonan): View
    {
        abort_unless($request->user()->canAccessLegacyPermohonan($legacyPermohonan), 403);

        $legacyPermohonan->load(['perunding', 'bangunan', 'ccc']);
        $siblings = $legacyPermohonan->siblingApplications();

        return view('bangunan.legacy.show', [
            'record' => $legacyPermohonan,
            'siblings' => $siblings,
            'hasSharedFileReference' => $siblings->isNotEmpty(),
        ]);
    }

    /**
     * @param  Builder<LegacyPermohonan>  $query
     * @return Builder<LegacyPermohonan>
     */
    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'LULUS' => $query
                ->whereNull('pmn_tkh_tolak')
                ->whereNull('pmn_tkh_batal')
                ->whereNull('pmn_tkh_tarik_balik')
                ->where(function (Builder $sub): void {
                    $sub->whereNotNull('pmn_tkh_lulus')
                        ->orWhereIn('pmn_status', ['T', 'CCC', 'CCC2a', 'CCC3', 'CCC4']);
                }),
            'DALAM_PROSES' => $query
                ->whereNull('pmn_tkh_tolak')
                ->whereNull('pmn_tkh_batal')
                ->whereNull('pmn_tkh_tarik_balik')
                ->whereNull('pmn_tkh_lulus')
                ->whereNull('pmn_tkh_tangguh'),
            'TANGGUH' => $query
                ->whereNull('pmn_tkh_tolak')
                ->whereNull('pmn_tkh_batal')
                ->whereNull('pmn_tkh_tarik_balik')
                ->whereNull('pmn_tkh_lulus')
                ->whereNotNull('pmn_tkh_tangguh'),
            'TOLAK' => $query->where(function (Builder $sub): void {
                $sub->whereNotNull('pmn_tkh_tolak')
                    ->orWhereNotNull('pmn_tkh_batal')
                    ->orWhereNotNull('pmn_tkh_tarik_balik');
            }),
            default => $query,
        };
    }
}
