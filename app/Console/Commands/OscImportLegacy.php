<?php

namespace App\Console\Commands;

use App\Services\Osc\LegacyImportService;
use Illuminate\Console\Command;
use Throwable;

class OscImportLegacy extends Command
{
    protected $signature = 'osc:import-legacy
        {--points : Import MJKP past decisions as points}
        {--images : Place legacy layout plans from world files}
        {--srid=990001 : Source SRID of the world files (990001 or 990002)}
        {--dir=O:\Projects\mjkp\web\plan : Folder holding the legacy plans}';

    protected $description = 'Import keputusan MJKP terdahulu dan pelan susun atur yang diletakkan.';

    public function handle(LegacyImportService $service): int
    {
        if (! $this->option('points') && ! $this->option('images')) {
            $this->error('Nyatakan --points dan/atau --images.');

            return self::FAILURE;
        }

        $srid = (int) $this->option('srid');
        if ($this->option('images') && ! in_array($srid, LegacyImportService::SRIDS, true)) {
            $this->error('--srid mesti 990001 atau 990002.');

            return self::FAILURE;
        }

        try {
            if ($this->option('points')) {
                $result = $service->importPoints();
                $this->table(['Hasil', 'Bilangan'], [
                    ['Rekod', $result['total']],
                    ['Ada titik', $result['with_point']],
                    [LegacyImportService::SKIP_NO_XY, $result['no_xy']],
                ]);
            }

            if ($this->option('images')) {
                $counts = $service->importImages((string) $this->option('dir'), $srid);
                $this->table(['Hasil', 'Bilangan'], collect($counts)->map(fn (int $n, string $k): array => [$k, $n])->values()->all());
            }
        } catch (Throwable $e) {
            $this->error('Import gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
