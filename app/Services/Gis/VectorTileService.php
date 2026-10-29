<?php

declare(strict_types=1);

namespace App\Services\Gis;

use App\Models\Gis\GisLayer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class VectorTileService
{
    private const EXTENT = 4096;

    private const BUFFER = 64;

    /** Returns the Mapbox Vector Tile (possibly empty) for the layer's active snapshot. */
    public function tile(GisLayer $layer, int $z, int $x, int $y): string
    {
        if ($layer->active_snapshot_id === null) {
            return '';
        }

        $bindings = [];
        $columns = '';
        foreach ($this->properties($layer) as $key) {
            // $key is validated against [a-z0-9_]+ in properties(); the attribute name itself is bound.
            $columns .= ', f.attributes ->> ?::text AS "'.$key.'"';
            $bindings[] = $key;
        }

        $sql = 'SELECT ST_AsMVT(q, ?::text, '.self::EXTENT.", 'geom') AS tile FROM (
            SELECT ST_AsMVTGeom(f.geom_web, ST_TileEnvelope(?::int, ?::int, ?::int), ".self::EXTENT.', '.self::BUFFER.', true) AS geom'.$columns.'
            FROM core.gis_features f
            WHERE f.snapshot_id = ?::bigint
              AND f.geom_web && ST_TileEnvelope(?::int, ?::int, ?::int)
        ) q WHERE q.geom IS NOT NULL';

        // Placeholder order in the text: MVT layer name, envelope z/x/y, property keys, snapshot, envelope z/x/y.
        $params = [$layer->id, $z, $x, $y, ...$bindings, $layer->active_snapshot_id, $z, $x, $y];

        $row = DB::selectOne($sql, $params);
        $tile = $row->tile ?? '';
        if (is_resource($tile)) {
            $tile = stream_get_contents($tile);
        }

        return $tile === false || $tile === null ? '' : $tile;
    }

    /** @return array<int, string> */
    private function properties(GisLayer $layer): array
    {
        $keys = config('gis.layers.'.$layer->id.'.properties', []);
        foreach ($keys as $key) {
            if (! is_string($key) || ! preg_match('/^[a-z0-9_]+$/', $key)) {
                throw new InvalidArgumentException('Kunci sifat lapisan GIS tidak sah.');
            }
        }

        return array_values($keys);
    }
}
