<?php

namespace App\Console\Commands;

use App\Models\Gis\GisLayer;
use App\Services\Gis\LayerReplicationService;
use Illuminate\Console\Command;
use Throwable;

class GisReplicate extends Command
{
    protected $signature = 'gis:replicate {layer? : ID lapisan} {--all : Replikasi semua lapisan aktif}';

    protected $description = 'Replikasi lapisan ArcGIS ke dalam PostGIS sebagai snapshot.';

    public function handle(LayerReplicationService $service): int
    {
        $query = GisLayer::query()->orderBy('sort_order')->orderBy('id');

        if ($this->argument('layer')) {
            $query->whereKey($this->argument('layer'));
        } elseif ($this->option('all')) {
            // Legacy-paper layers refresh only through osc:import-legacy --points (it also rebuilds core.osc_legacy_papers).
            $query->where('is_active', true)->where('kind', '!=', 'legacy_papers');
        } else {
            $this->error('Nyatakan ID lapisan atau gunakan --all.');

            return self::FAILURE;
        }

        $layers = $query->get();
        if ($layers->isEmpty()) {
            $this->error('Lapisan tidak ditemui.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($layers as $layer) {
            if ($layer->kind === 'legacy_papers') {
                $failed = true;
                $this->error("Lapisan {$layer->id} dikemas kini melalui osc:import-legacy --points sahaja.");

                continue;
            }
            try {
                $snapshot = $service->replicate($layer);
                $this->line("Lapisan {$layer->id}: {$snapshot->feature_count} rekod");
            } catch (Throwable $e) {
                $failed = true;
                $this->error("Lapisan {$layer->id} gagal: ".$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
