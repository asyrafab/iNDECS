<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BangunanLegacySyncService;
use Illuminate\Console\Command;

final class SyncBangunanLegacy extends Command
{
    protected $signature = 'bangunan:sync-legacy {--json= : Optional path to a scrubbed JSON snapshot}';

    protected $description = 'Synchronise read-only legacy MPSP_Bangunan records into bangunan.legacy_* and report reconciliation counts';

    public function handle(BangunanLegacySyncService $service): int
    {
        $snapshot = null;
        $jsonPath = $this->option('json');

        if (is_string($jsonPath) && $jsonPath !== '') {
            if (! is_file($jsonPath)) {
                $this->error("Fail snapshot tidak ditemui: {$jsonPath}");

                return self::FAILURE;
            }

            $decoded = json_decode((string) file_get_contents($jsonPath), true);

            if (! is_array($decoded) || ! isset($decoded['permohonan'], $decoded['perunding'], $decoded['bangunan'], $decoded['ccc'])) {
                $this->error('Format JSON snapshot tidak sah.');

                return self::FAILURE;
            }

            $snapshot = $decoded;
        }

        $counts = $service->sync($snapshot);

        $this->info('Penyegerakan baca-sahaja bangunan.legacy_* selesai.');
        $this->table(
            ['Jadual / Metrik', 'Bilangan Rekod'],
            [
                ['bangunan.legacy_permohonan (jumlah)', (string) $counts['permohonan']],
                ['bangunan.legacy_permohonan (rekod ujian dikecualikan)', (string) $counts['excluded_test_records']],
                ['bangunan.legacy_perunding', (string) $counts['perunding']],
                ['bangunan.legacy_bangunan', (string) $counts['bangunan']],
                ['bangunan.legacy_ccc', (string) $counts['ccc']],
            ],
        );

        return self::SUCCESS;
    }
}
