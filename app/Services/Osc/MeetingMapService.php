<?php

declare(strict_types=1);

namespace App\Services\Osc;

use App\Enums\Osc\AgendaItemStatus;
use App\Models\ApplicationType;
use App\Models\Gis\GisLayer;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds the GeoJSON FeatureCollection shown on the meeting map: agenda items the user may see (never returned ones;
 * items not yet accepted carry a BM status_label), their earlier tablings, and (for users with the eOSC+ view grant)
 * past decisions within 500 m.
 */
final class MeetingMapService
{
    private const LEGACY_RADIUS_METRES = 500;

    private const LEGACY_LIMIT = 500;

    /** Decision labels for core.osc_legacy_papers.status_code. */
    private const DECISIONS = [
        '1' => 'Lulus Dgn Syarat Tambahan',
        '2' => 'Lulus Dgn Syarat Teknikal',
        '3' => 'Tangguh',
        '4' => 'Tolak',
    ];

    /** @return array{type: string, features: array<int, array<string, mixed>>, properties: array<string, mixed>} */
    public function forMeeting(Meeting $meeting, User $user): array
    {
        $features = [];
        $unmapped = [];
        $geometries = [];

        $items = $meeting->items()->with('application')->get();
        $layouts = $this->layouts('agenda_item', $items->pluck('id')->all());
        $typeNames = ApplicationType::query()
            ->whereIn('id', $items->map(fn (AgendaItem $item) => $item->application_type_id ?? $item->application?->application_type_id)->filter()->unique()->values())
            ->pluck('name', 'id');
        $developers = $this->developers($items->pluck('application_id')->filter()->unique()->values()->all());
        foreach ($items as $item) {
            $item->setRelation('meeting', $meeting);
            // Returned items have left the agenda; they are not shown at all.
            if ($item->status === AgendaItemStatus::Returned || ! OscAccess::canViewItem($user, $item)) {
                continue;
            }

            [$geometry, $source] = $this->resolveGeometry($item);
            if ($geometry === null && isset($layouts[$item->id])) {
                // Nothing else locates the item, but its placed layout plan does: use the plan's footprint.
                $corners = $layouts[$item->id]['layout_corners'];
                [$geometry, $source] = [['type' => 'Polygon', 'coordinates' => [[...$corners, $corners[0]]]], 'layout'];
            }
            if ($geometry === null) {
                $unmapped[] = ['id' => $item->id, 'title' => $item->title]
                    + ($item->status === AgendaItemStatus::Accepted ? [] : ['status_label' => $item->status->label()]);
            } else {
                $geometries[] = $geometry;
                $features[] = $this->feature($geometry, [
                    'kind' => 'item',
                    'id' => $item->id,
                    'title' => $item->title,
                    'reference' => $item->reference,
                    'type_label' => $typeNames[$item->application_type_id ?? $item->application?->application_type_id] ?? $item->standalone_type,
                    'developer' => $developers[$item->application_id] ?? null,
                    'purpose' => $item->purpose->label(),
                    'department_id' => $item->department_id,
                    'status' => $item->status->value,
                    'position' => $item->position,
                    'paper_url' => $this->paperUrl($item),
                    'layout_url' => $layouts[$item->id]['layout_url'] ?? null,
                    'layout_corners' => $layouts[$item->id]['layout_corners'] ?? null,
                    'geometry_source' => $source,
                ] + ($item->status === AgendaItemStatus::Accepted ? [] : ['status_label' => $item->status->label()]));
            }

            foreach ($this->earlierTablings($item, $user) as $earlier) {
                [$earlierGeometry] = $this->resolveGeometry($earlier);
                if ($earlierGeometry === null) {
                    continue;
                }
                $geometries[] = $earlierGeometry;
                $features[] = $this->feature($earlierGeometry, [
                    'kind' => 'earlier',
                    'id' => $earlier->id,
                    'item_id' => $item->id,
                    'title' => $earlier->title,
                    'reference' => $earlier->reference,
                    'meeting_id' => $earlier->meeting_id,
                    'purpose' => $earlier->purpose->label(),
                    'status' => $earlier->status->value,
                ]);
            }
        }

        if ($geometries !== [] && ($user->canPerformOsc('osc.meeting.view') || OscAccess::canManage($user))) {
            array_push($features, ...$this->pastDecisions($geometries));
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'properties' => ['unmapped' => $unmapped],
        ];
    }

    /**
     * Earlier items of the same application, nearest first, that the user may see.
     *
     * @return array<int, AgendaItem>
     */
    private function earlierTablings(AgendaItem $item, User $user): array
    {
        $earlier = [];
        $visited = [$item->id => true];
        $current = $item;
        while ($current->previous_item_id !== null && ! isset($visited[$current->previous_item_id])) {
            $previous = AgendaItem::query()->with(['meeting', 'application'])->find($current->previous_item_id);
            if ($previous === null) {
                break;
            }
            $visited[$previous->id] = true;
            if (OscAccess::canViewItem($user, $previous)) {
                $earlier[] = $previous;
            }
            $current = $previous;
        }

        return $earlier;
    }

    /** @return array{0: array<string, mixed>|null, 1: string|null} GeoJSON geometry and its source. */
    private function resolveGeometry(AgendaItem $item): array
    {
        $own = DB::selectOne('SELECT ST_AsGeoJSON(boundary) AS geojson FROM core.osc_agenda_items WHERE id = ? AND boundary IS NOT NULL', [$item->id]);
        if ($own !== null) {
            return [json_decode($own->geojson, true), 'item'];
        }

        if ($item->application_id === null) {
            return [null, null];
        }

        $site = DB::selectOne(
            'SELECT ST_AsGeoJSON(s.boundary) AS geojson FROM sites s
             JOIN application_sites a ON a.site_id = s.id
             WHERE a.application_id = ? AND s.boundary IS NOT NULL
             ORDER BY a.is_primary DESC, a.id LIMIT 1',
            [$item->application_id],
        );
        if ($site !== null) {
            return [json_decode($site->geojson, true), 'site'];
        }

        $lots = $this->lotGeometry($item->application_id);

        return $lots === null ? [null, null] : [$lots, 'lot'];
    }

    /**
     * Union of the lots-layer features matching the application's lots. A lot takes part only when its lot number,
     * mukim and district are all present after normalisation, and only when it matches exactly one feature
     * (otherwise the location would be a guess). Both sides use the same normalisation (see normalise()), so
     * "LOT 03899" / "3899" and "Mukim 04" / "MUKIM 4" agree.
     *
     * @return array<string, mixed>|null
     */
    private function lotGeometry(string $applicationId): ?array
    {
        $layer = GisLayer::query()->find('lots');
        $mapping = $layer?->match_attributes;
        if ($layer === null || $layer->active_snapshot_id === null || ! is_array($mapping)) {
            return null;
        }
        $keys = [];
        foreach (['lot_number', 'mukim', 'district'] as $column) {
            $attribute = $mapping[$column] ?? null;
            if (! is_string($attribute) || ! preg_match('/^[a-z0-9_]+$/', $attribute)) {
                return null;
            }
            $keys[$column] = $attribute;
        }

        $lots = DB::table('lots')->join('application_lots', 'application_lots.lot_id', '=', 'lots.id')
            ->where('application_lots.application_id', $applicationId)->get(['lots.lot_number', 'lots.mukim', 'lots.district']);

        $featureIds = [];
        foreach ($lots as $lot) {
            // Attribute names are validated above; the lot expression must match the gis_features_lot_norm_idx definition.
            $featureLot = "core.gis_norm_lot(f.attributes ->> '{$keys['lot_number']}')";
            $featureMukim = $this->normalise('mukim', "f.attributes ->> '{$keys['mukim']}'");
            $featureDistrict = $this->normalise('district', "f.attributes ->> '{$keys['district']}'");
            $paramMukim = $this->normalise('mukim', '?::text');
            $paramDistrict = $this->normalise('district', '?::text');
            $rows = DB::select(
                "SELECT f.id FROM core.gis_features f
                 WHERE f.snapshot_id = ?::bigint
                   AND core.gis_norm_lot(?::text) <> '' AND $paramMukim <> '' AND $paramDistrict <> ''
                   AND $featureLot = core.gis_norm_lot(?::text)
                   AND $featureMukim = $paramMukim
                   AND $featureDistrict = $paramDistrict
                 LIMIT 2",
                [$layer->active_snapshot_id,
                    (string) $lot->lot_number, (string) $lot->mukim, (string) $lot->district,
                    (string) $lot->lot_number, (string) $lot->mukim, (string) $lot->district],
            );
            if (count($rows) === 1) { // Zero is unmatched; more than one is ambiguous and ignored.
                $featureIds[] = $rows[0]->id;
            }
        }
        if ($featureIds === []) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Multi(ST_Union(f.geom))) AS geojson FROM core.gis_features f WHERE f.id IN ('.implode(',', array_fill(0, count($featureIds), '?')).')',
            $featureIds,
        );

        return $row?->geojson === null ? null : json_decode($row->geojson, true);
    }

    /** SQL expression normalising a lot, mukim, district or section value for comparison. $expr is trusted SQL, never user input. */
    private function normalise(string $column, string $expr): string
    {
        $upper = "upper(btrim(regexp_replace($expr, '\\s+', ' ', 'g')))";

        return match ($column) {
            // "LOT 03899", "No. Lot 3899", "3899" -> "3899"
            'lot_number' => "regexp_replace(regexp_replace($upper, '^(NO\\.? ?)?(LOT|LT)\\.? ?', ''), '^0+(?=.)', '')",
            // "Mukim 04", "MK 4", "4" -> "4"
            'mukim', 'section' => "regexp_replace(regexp_replace($upper, '^(MUKIM|MK|SEKSYEN|SEK)\\.? ?', ''), '^0+(?=.)', '')",
            default => $upper,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $geometries
     * @return array<int, array<string, mixed>>
     */
    private function pastDecisions(array $geometries): array
    {
        $conditions = [];
        $bindings = [];
        foreach ($geometries as $geometry) {
            $json = json_encode($geometry);
            // The bbox test lets the GiST index prune before the exact geography distance check (0.01 degrees is over 1 km).
            $conditions[] = '(p.geom && ST_Expand(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 0.01)
                AND ST_DWithin(p.geom::geography, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography, ?))';
            array_push($bindings, $json, $json, self::LEGACY_RADIUS_METRES);
        }

        $rows = DB::select(
            'SELECT p.id, p.title, p.year, p.status_code, p.rujukan, ST_AsGeoJSON(p.geom) AS geojson
             FROM core.osc_legacy_papers p
             WHERE p.geom IS NOT NULL AND ('.implode(' OR ', $conditions).')
             ORDER BY p.id LIMIT '.self::LEGACY_LIMIT,
            $bindings,
        );

        $layouts = $this->layouts('legacy', array_map(fn ($row): string => (string) $row->id, $rows));

        return array_map(fn ($row): array => $this->feature(json_decode($row->geojson, true), [
            'kind' => 'legacy',
            'id' => $row->id,
            'title' => $row->title,
            'year' => $row->year,
            'rujukan' => $row->rujukan,
            'decision' => self::DECISIONS[(string) $row->status_code] ?? 'Tiada maklumat',
        ] + ($layouts[(string) $row->id] ?? [])), $rows);
    }

    /**
     * Positioned layout plans for the given owners, keyed by owner id. The caller has already checked that the user may
     * see each owner; the image itself is checked again by osc.layout.show.
     *
     * @param  array<int, string>  $ownerIds
     * @return array<string, array{layout_url: string, layout_corners: array<int, array<int, float>>}>
     */
    private function layouts(string $ownerType, array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }
        $rows = DB::select(
            'SELECT id, owner_id, ST_AsGeoJSON(corners) AS geojson FROM core.osc_layout_images
             WHERE owner_type = ? AND owner_id IN ('.implode(',', array_fill(0, count($ownerIds), '?')).')',
            [$ownerType, ...array_values($ownerIds)],
        );
        $result = [];
        foreach ($rows as $row) {
            $ring = json_decode($row->geojson, true)['coordinates'][0] ?? [];
            $result[(string) $row->owner_id] = [
                'layout_url' => route('osc.layout.show', ['layoutImage' => $row->id]),
                'layout_corners' => array_slice($ring, 0, 4),
            ];
        }

        return $result;
    }

    /**
     * Name of each application's primary applicant (the developer), keyed by application id.
     *
     * @param  array<int, string>  $applicationIds
     * @return array<string, string>
     */
    private function developers(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        return DB::table('application_parties')->join('parties', 'parties.id', '=', 'application_parties.party_id')
            ->whereIn('application_parties.application_id', $applicationIds)
            ->where('application_parties.role', 'applicant')
            ->orderByDesc('application_parties.is_primary')->orderBy('application_parties.id')
            ->get(['application_parties.application_id', 'parties.name'])
            ->unique('application_id')
            ->pluck('name', 'application_id')
            ->all();
    }

    private function paperUrl(AgendaItem $item): ?string
    {
        $paper = $item->papers()->first();

        return $paper === null ? null : route('osc.papers.show', $paper);
    }

    /**
     * @param  array<string, mixed>|null  $geometry
     * @param  array<string, mixed>  $properties
     * @return array{type: string, geometry: array<string, mixed>|null, properties: array<string, mixed>}
     */
    private function feature(?array $geometry, array $properties): array
    {
        return ['type' => 'Feature', 'geometry' => $geometry, 'properties' => $properties];
    }
}
