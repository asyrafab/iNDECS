<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class BangunanLegacySyncService
{
    /**
     * Load a scrubbed snapshot of legacy MPSP_Bangunan records into bangunan.legacy_*
     * and return reconciliation counts.
     *
     * @param  array<string, list<array<string, mixed>>>|null  $snapshot
     * @return array{permohonan: int, perunding: int, bangunan: int, ccc: int, excluded_test_records: int}
     */
    public function sync(?array $snapshot = null): array
    {
        $payload = $snapshot ?? $this->defaultScrubbedSnapshot();
        $now = now();

        return DB::transaction(function () use ($payload, $now): array {
            foreach ($payload['permohonan'] as $row) {
                DB::table('bangunan.legacy_permohonan')->updateOrInsert(
                    ['pmn_id' => $row['pmn_id']],
                    [...$row, 'synced_at' => $now],
                );
            }

            foreach ($payload['perunding'] as $row) {
                DB::table('bangunan.legacy_perunding')->updateOrInsert(
                    ['prd_id' => $row['prd_id']],
                    [...$row, 'synced_at' => $now],
                );
            }

            foreach ($payload['bangunan'] as $row) {
                DB::table('bangunan.legacy_bangunan')->updateOrInsert(
                    ['bgn_id' => $row['bgn_id']],
                    [...$row, 'synced_at' => $now],
                );
            }

            foreach ($payload['ccc'] as $row) {
                DB::table('bangunan.legacy_ccc')->updateOrInsert(
                    ['ccc_id' => $row['ccc_id']],
                    [...$row, 'synced_at' => $now],
                );
            }

            return [
                'permohonan' => DB::table('bangunan.legacy_permohonan')->count(),
                'perunding' => DB::table('bangunan.legacy_perunding')->count(),
                'bangunan' => DB::table('bangunan.legacy_bangunan')->count(),
                'ccc' => DB::table('bangunan.legacy_ccc')->count(),
                'excluded_test_records' => DB::table('bangunan.legacy_permohonan')
                    ->where('is_excluded_test_record', true)
                    ->count(),
            ];
        });
    }

    /**
     * Scrubbed legacy snapshot based on the verified 2024 relational structure
     * in ANALISIS_SENIBINA_PANGKALAN_DATA_2024.md and 10-pemetaan-dan-migrasi.md.
     * Personal names are replaced with synthetic placeholders per §11.1.
     *
     * @return array{
     *     permohonan: list<array<string, mixed>>,
     *     perunding: list<array<string, mixed>>,
     *     bangunan: list<array<string, mixed>>,
     *     ccc: list<array<string, mixed>>
     * }
     */
    public function defaultScrubbedSnapshot(): array
    {
        return [
            'permohonan' => [
                [
                    'pmn_id' => 34110,
                    'pmn_no_siri' => '20221804',
                    'pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'pmn_tajuk' => 'CADANGAN SKIM PERUMAHAN FASA 1 DI ATAS LOT 1241, 345 & 1269, MUKIM 20, SPT',
                    'pmn_pemohon' => 'Pemaju Bestari Sintetik Sdn. Bhd.',
                    'pmn_tuan_tanah' => 'Pemaju Bestari Sintetik Sdn. Bhd.',
                    'pmn_no_lot' => '1241, 345 & 1269',
                    'pmn_tempat' => 'JALAN KUBANG ULU',
                    'sd_mukim' => '20',
                    'sd_daerah_kod' => 'T',
                    'district' => 'Seberang Perai Tengah',
                    'sk_kategori' => 'SEKIM PERUMAHAN/SEKIM KILANG',
                    'pmn_status' => 'T',
                    'pmn_tkh_pohon' => '2022-05-12',
                    'pmn_tkh_bayar' => '2022-05-12',
                    'pmn_tkh_lulus' => '2022-11-18',
                    'pmn_tkh_tolak' => null,
                    'pmn_tkh_batal' => null,
                    'pmn_tkh_tarik_balik' => null,
                    'pmn_tkh_tangguh' => null,
                    'pmn_bayaran' => 4850.00,
                    'pmn_bayaran_cagaran' => 2000.00,
                    'pmn_no_rjk_perancang' => 'MBSP/70/20-50/13',
                    'pmn_keputusan_osc' => 'LULUS',
                    'pmn_keputusan_ydp' => 'LULUS',
                    'is_excluded_test_record' => false,
                ],
                [
                    'pmn_id' => 36298,
                    'pmn_no_siri' => '20253317',
                    'pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'pmn_tajuk' => 'PERMIT PINDAAN KECIL KEPADA PELAN LULUS FASA 2 DI ATAS LOT 1241, 345 & 1269, MUKIM 20, SPT',
                    'pmn_pemohon' => 'Pemaju Bestari Sintetik Sdn. Bhd.',
                    'pmn_tuan_tanah' => 'Pemaju Bestari Sintetik Sdn. Bhd.',
                    'pmn_no_lot' => '1241, 345 & 1269',
                    'pmn_tempat' => 'JALAN KUBANG ULU',
                    'sd_mukim' => '20',
                    'sd_daerah_kod' => 'T',
                    'district' => 'Seberang Perai Tengah',
                    'sk_kategori' => 'SEKIM PERUMAHAN/SEKIM KILANG',
                    'pmn_status' => 'B',
                    'pmn_tkh_pohon' => '2024-12-27',
                    'pmn_tkh_bayar' => '2024-12-27',
                    'pmn_tkh_lulus' => '2025-02-27',
                    'pmn_tkh_tolak' => null,
                    'pmn_tkh_batal' => null,
                    'pmn_tkh_tarik_balik' => null,
                    'pmn_tkh_tangguh' => null,
                    'pmn_bayaran' => 100.00,
                    'pmn_bayaran_cagaran' => 0.00,
                    'pmn_no_rjk_perancang' => 'MBSP/70/20-50/13',
                    'pmn_keputusan_osc' => 'LULUS BERSYARAT',
                    'pmn_keputusan_ydp' => 'LULUS',
                    'is_excluded_test_record' => false,
                ],
                [
                    'pmn_id' => 36312,
                    'pmn_no_siri' => '20253402',
                    'pmn_no_rujukan' => 'MBSP/40/10-14/88',
                    'pmn_tajuk' => 'CADANGAN BINAAN KILANG SESEBUAH 2 TINGKAT DI ATAS LOT 4012, MUKIM 14, SPU',
                    'pmn_pemohon' => 'Perindustrian Utara Sintetik Sdn. Bhd.',
                    'pmn_tuan_tanah' => 'Perindustrian Utara Sintetik Sdn. Bhd.',
                    'pmn_no_lot' => 'Lot 4012',
                    'pmn_tempat' => 'KAWASAN PERINDUSTRIAN MAK MANDIN',
                    'sd_mukim' => '14',
                    'sd_daerah_kod' => 'U',
                    'district' => 'Seberang Perai Utara',
                    'sk_kategori' => 'KILANG SESEBUAH',
                    'pmn_status' => 'L',
                    'pmn_tkh_pohon' => '2024-11-15',
                    'pmn_tkh_bayar' => '2024-11-15',
                    'pmn_tkh_lulus' => null,
                    'pmn_tkh_tolak' => null,
                    'pmn_tkh_batal' => null,
                    'pmn_tkh_tarik_balik' => null,
                    'pmn_tkh_tangguh' => null,
                    'pmn_bayaran' => 3200.00,
                    'pmn_bayaran_cagaran' => 1500.00,
                    'pmn_no_rjk_perancang' => 'MBSP/70/10-14/88',
                    'pmn_keputusan_osc' => null,
                    'pmn_keputusan_ydp' => null,
                    'is_excluded_test_record' => false,
                ],
                [
                    'pmn_id' => 36345,
                    'pmn_no_siri' => '20253519',
                    'pmn_no_rujukan' => 'MBSP/40/30-11/04',
                    'pmn_tajuk' => 'CADANGAN TAMBAHAN DAN UBAHSUAI RUMAH TERES 2 TINGKAT DI ATAS LOT 889, MUKIM 11, SPS',
                    'pmn_pemohon' => 'Pemohon Kediaman Sintetik',
                    'pmn_tuan_tanah' => 'Pemohon Kediaman Sintetik',
                    'pmn_no_lot' => 'PT 889',
                    'pmn_tempat' => 'TAMAN JAWI INDAH',
                    'sd_mukim' => '11',
                    'sd_daerah_kod' => 'S',
                    'district' => 'Seberang Perai Selatan',
                    'sk_kategori' => 'TAMBAHAN / UBAHSUAI KEDIAMAN',
                    'pmn_status' => 'B',
                    'pmn_tkh_pohon' => '2024-10-03',
                    'pmn_tkh_bayar' => '2024-10-03',
                    'pmn_tkh_lulus' => null,
                    'pmn_tkh_tolak' => null,
                    'pmn_tkh_batal' => null,
                    'pmn_tkh_tarik_balik' => null,
                    'pmn_tkh_tangguh' => '2024-11-08',
                    'pmn_bayaran' => 280.00,
                    'pmn_bayaran_cagaran' => 500.00,
                    'pmn_no_rjk_perancang' => null,
                    'pmn_keputusan_osc' => 'TANGGUH',
                    'pmn_keputusan_ydp' => null,
                    'is_excluded_test_record' => false,
                ],
                [
                    'pmn_id' => 9,
                    'pmn_no_siri' => '20090009',
                    'pmn_no_rujukan' => 'MPSP/40/TEST/01',
                    'pmn_tajuk' => 'REKOD UJIAN SISTEM LAMA (DIKECUALIKAN)',
                    'pmn_pemohon' => 'Ujian Dalaman',
                    'pmn_tuan_tanah' => 'Ujian Dalaman',
                    'pmn_no_lot' => '000',
                    'pmn_tempat' => 'TEST',
                    'sd_mukim' => '01',
                    'sd_daerah_kod' => 'T',
                    'district' => 'Seberang Perai Tengah',
                    'sk_kategori' => 'LAIN-LAIN',
                    'pmn_status' => 'B',
                    'pmn_tkh_pohon' => '2015-01-01',
                    'pmn_tkh_bayar' => null,
                    'pmn_tkh_lulus' => null,
                    'pmn_tkh_tolak' => null,
                    'pmn_tkh_batal' => null,
                    'pmn_tkh_tarik_balik' => null,
                    'pmn_tkh_tangguh' => null,
                    'pmn_bayaran' => 0.00,
                    'pmn_bayaran_cagaran' => 0.00,
                    'pmn_no_rjk_perancang' => null,
                    'pmn_keputusan_osc' => null,
                    'pmn_keputusan_ydp' => null,
                    'is_excluded_test_record' => true,
                ],
            ],
            'perunding' => [
                [
                    'prd_id' => 899855,
                    'prd_pmn_no_siri' => '20253317',
                    'prd_perunding' => 'Firma Arkitek Sintetik Perai',
                    'prd_pelan' => 'PELAN BANGUNAN',
                    'prd_arkitek' => 'Prinsipal Arkitek Sintetik 01',
                    'prd_no_daftar' => 'LAM/A-0072',
                    'prd_tel' => '04-3900001',
                ],
                [
                    'prd_id' => 899910,
                    'prd_pmn_no_siri' => '20253402',
                    'prd_perunding' => 'Jurutera Perunding Utara PLT',
                    'prd_pelan' => 'PELAN STRUKTUR',
                    'prd_arkitek' => 'Prinsipal Jurutera Sintetik 02',
                    'prd_no_daftar' => 'BEM/C-1180',
                    'prd_tel' => '04-3310002',
                ],
                [
                    'prd_id' => 899940,
                    'prd_pmn_no_siri' => '20253519',
                    'prd_perunding' => 'Studio Reka Bentuk Selatan',
                    'prd_pelan' => 'PELAN TAMBAHAN / UBAHSUAI',
                    'prd_arkitek' => 'Pelukis Pelan Berdaftar Sintetik 03',
                    'prd_no_daftar' => 'LAM/BD-0304',
                    'prd_tel' => '04-5820003',
                ],
            ],
            'bangunan' => [
                [
                    'bgn_id' => 75055,
                    'bgn_pmn_no_siri' => '20253317',
                    'bgn_pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'bgn_unit' => 61,
                    'bgn_tingkat' => 2,
                    'sb_jenis_binaan' => 'Rumah Teres Kos Sederhana',
                    'bgn_luas' => 0.00,
                    'bgn_jumlah' => 100.00,
                    'bgn_chk_spah' => false,
                    'bgn_chk_ottv' => false,
                    'bgn_chk_oku' => false,
                ],
                [
                    'bgn_id' => 75056,
                    'bgn_pmn_no_siri' => '20253317',
                    'bgn_pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'bgn_unit' => 12,
                    'bgn_tingkat' => 2,
                    'sb_jenis_binaan' => 'Rumah Berkembar',
                    'bgn_luas' => 0.00,
                    'bgn_jumlah' => 0.00,
                    'bgn_chk_spah' => false,
                    'bgn_chk_ottv' => false,
                    'bgn_chk_oku' => false,
                ],
                [
                    'bgn_id' => 75057,
                    'bgn_pmn_no_siri' => '20253317',
                    'bgn_pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'bgn_unit' => 1,
                    'bgn_tingkat' => 1,
                    'sb_jenis_binaan' => 'Depo Sampah',
                    'bgn_luas' => 0.00,
                    'bgn_jumlah' => 140.00,
                    'bgn_chk_spah' => false,
                    'bgn_chk_ottv' => false,
                    'bgn_chk_oku' => false,
                ],
                [
                    'bgn_id' => 75120,
                    'bgn_pmn_no_siri' => '20253402',
                    'bgn_pmn_no_rujukan' => 'MBSP/40/10-14/88',
                    'bgn_unit' => 1,
                    'bgn_tingkat' => 2,
                    'sb_jenis_binaan' => 'Kilang Sesebuah',
                    'bgn_luas' => 2450.00,
                    'bgn_jumlah' => 3200.00,
                    'bgn_chk_spah' => true,
                    'bgn_chk_ottv' => true,
                    'bgn_chk_oku' => true,
                ],
                [
                    'bgn_id' => 75190,
                    'bgn_pmn_no_siri' => '20253519',
                    'bgn_pmn_no_rujukan' => 'MBSP/40/30-11/04',
                    'bgn_unit' => 1,
                    'bgn_tingkat' => 2,
                    'sb_jenis_binaan' => 'Tambahan Rumah Teres',
                    'bgn_luas' => 48.50,
                    'bgn_jumlah' => 280.00,
                    'bgn_chk_spah' => false,
                    'bgn_chk_ottv' => false,
                    'bgn_chk_oku' => false,
                ],
            ],
            'ccc' => [
                [
                    'ccc_id' => 114466,
                    'ccc_pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'sccc_id' => 1,
                    'sccc_jenis' => 'B',
                    'sccc_komponen' => 'Mula Kerja Bangunan',
                    'ccc_status' => 'Y',
                    'ccc_tkh_ccc' => '2017-03-09',
                ],
                [
                    'ccc_id' => 114457,
                    'ccc_pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'sccc_id' => 2,
                    'sccc_jenis' => 'G1',
                    'sccc_komponen' => 'Kerja-Kerja Tanah',
                    'ccc_status' => 'Y',
                    'ccc_tkh_ccc' => '2026-05-22',
                ],
                [
                    'ccc_id' => 114460,
                    'ccc_pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'sccc_id' => 5,
                    'sccc_jenis' => 'G4',
                    'sccc_komponen' => 'Struktur Konkrit Tetulang',
                    'ccc_status' => 'Y',
                    'ccc_tkh_ccc' => '2026-05-22',
                ],
                [
                    'ccc_id' => 114483,
                    'ccc_pmn_no_rujukan' => 'MBSP/40/20-50/13(PR1)',
                    'sccc_id' => 23,
                    'sccc_jenis' => 'F',
                    'sccc_komponen' => 'Perakuan Siap dan Pematuhan (CCC Penuh)',
                    'ccc_status' => 'Y',
                    'ccc_tkh_ccc' => '2026-05-22',
                ],
            ],
        ];
    }
}
