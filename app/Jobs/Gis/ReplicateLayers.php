<?php

namespace App\Jobs\Gis;

use App\Models\Gis\GisLayer;
use App\Models\User;
use App\Services\Gis\LayerReplicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class ReplicateLayers implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /** Runs on the dedicated 'gis' connection, whose retry_after outlasts the one-hour timeout (config/queue.php). */
    public function __construct(public readonly ?User $actor = null)
    {
        $this->onConnection('gis');
    }

    public function handle(LayerReplicationService $service): void
    {
        $failed = [];
        // Legacy-paper layers refresh only through osc:import-legacy --points, which also rebuilds core.osc_legacy_papers.
        foreach (GisLayer::query()->where('is_active', true)->where('kind', '!=', 'legacy_papers')->orderBy('sort_order')->orderBy('id')->get() as $layer) {
            try {
                $service->replicate($layer, $this->actor);
            } catch (Throwable) {
                $failed[] = $layer->id;
            }
        }

        if ($failed !== []) {
            throw new RuntimeException('Replikasi gagal untuk: '.implode(', ', $failed));
        }
    }
}
