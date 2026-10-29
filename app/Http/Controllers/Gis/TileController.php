<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gis;

use App\Http\Controllers\Controller;
use App\Models\Gis\GisLayer;
use App\Services\Gis\VectorTileService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TileController extends Controller
{
    /** The map client requests vector tiles from zoom 12 only; lower zooms would aggregate a whole layer per tile. */
    public const MIN_ZOOM = 12;

    public const MAX_ZOOM = 22;

    public function show(Request $request, GisLayer $layer, int $z, int $x, int $y, VectorTileService $tiles): Response
    {
        abort_unless($request->user()?->canUseGisTiles(), 403);
        abort_unless(
            $layer->is_active && $layer->kind === 'reference' && $layer->active_snapshot_id !== null
                && $z >= self::MIN_ZOOM && $z <= self::MAX_ZOOM && $x < 2 ** $z && $y < 2 ** $z,
            404,
        );

        $response = new Response($tiles->tile($layer, $z, $x, $y), 200, [
            'Content-Type' => 'application/vnd.mapbox-vector-tile',
            'Cache-Control' => 'private, max-age=86400',
        ]);
        $response->setEtag((string) $layer->active_snapshot_id);
        $response->isNotModified($request);

        return $response;
    }
}
