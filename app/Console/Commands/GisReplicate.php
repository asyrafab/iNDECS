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
            $query->where('is_active', true);
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
