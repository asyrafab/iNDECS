<?php

declare(strict_types=1);

namespace App\Services\Gis;

use App\Models\Gis\GisLayer;

/** Builds the client config for GisMap.create: imagery sources, vector-tile templates and the region box. */
final class MapConfig
{
    /**
     * @param  list<string>|null  $layerIds  null keeps every active reference layer
     * @return list<array{id: string, label: string, tileUrlTemplate: string}>
     */
    public static function layers(string $cadastreLabel, ?array $layerIds = null): array
    {
        return GisLayer::query()->where('kind', 'reference')->where('is_active', true)
            ->whereNotNull('active_snapshot_id')
            ->when($layerIds !== null, fn ($query) => $query->whereIn('id', $layerIds))
            ->orderBy('sort_order')->get()
            ->map(fn (GisLayer $layer): array => [
                'id' => $layer->id,
                'label' => $layer->id === 'lots' ? $cadastreLabel : $layer->label,
                'tileUrlTemplate' => str_replace('/0/0/0.pbf', '/{z}/{x}/{y}.pbf', route('gis.tiles', ['layer' => $layer->id, 'z' => 0, 'x' => 0, 'y' => 0])),
            ])->values()->all();
    }

    /** @return list<array{key: string, label: string, url: string, attribution: string}> */
    public static function imagery(): array
    {
        $imagery = [];
        foreach ((array) config('gis.imagery') as $key => $source) {
            if (($source['url'] ?? '') === '') {
                continue;
            }
            $imagery[] = ['key' => $key, 'label' => $source['label'], 'url' => $source['url'], 'attribution' => $source['attribution']];
        }

        return $imagery;
    }

    /** Config for the boundary drawing field: imagery and the lots layer only, no item data. */
    public static function forBoundaryField(): array
    {
        return [
            'layers' => self::layers('Lot kadaster', ['lots']),
            'imagery' => self::imagery(),
            'regionBbox' => config('gis.region_bbox'),
            'labels' => ['cadastre' => 'Lot kadaster', 'imagery' => 'Imej satelit', 'street' => 'Peta jalan', 'orthophoto' => 'Ortofoto MBSP', 'imageryUnavailable' => 'Imej satelit tidak tersedia. Lapisan tempatan masih dipaparkan.'],
        ];
    }
}
