<?php

declare(strict_types=1);

namespace App\Http\Controllers\Osc;

use App\Http\Controllers\Controller;
use App\Models\Gis\GisLayer;
use App\Models\Osc\Meeting;
use App\Services\Osc\MeetingMapService;
use App\Services\Osc\OscAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class MeetingMapController extends Controller
{
    /** BM copy for the map UI. Item data never goes in here; it comes only from map-data. */
    private const LABELS = [
        'panel' => 'Peta',
        'application' => 'Permohonan',
        'layout' => 'Pelan susun atur',
        'cadastre' => 'Lot kadaster',
        'imagery' => 'Imej satelit',
        'street' => 'Peta jalan',
        'orthophoto' => 'Ortofoto MBSP',
        'earlier' => 'Keputusan terdahulu',
        'measureDistance' => 'Ukur jarak',
        'measureArea' => 'Ukur keluasan',
        'compare' => 'Bandingkan',
        'streetView' => 'Street View',
        'zoomToSite' => 'Zum ke tapak',
        'imageryUnavailable' => 'Imej satelit tidak tersedia. Lapisan tempatan masih dipaparkan.',
    ];

    public function show(Request $request, Meeting $meeting): View
    {
        abort_unless(OscAccess::canViewMeeting($request->user(), $meeting), 403);

        $layers = GisLayer::query()->where('kind', 'reference')->where('is_active', true)
            ->whereNotNull('active_snapshot_id')->orderBy('sort_order')->get()
            ->map(fn (GisLayer $layer): array => [
                'id' => $layer->id,
                'label' => $layer->id === 'lots' ? self::LABELS['cadastre'] : $layer->label,
                'tileUrlTemplate' => str_replace('/0/0/0.pbf', '/{z}/{x}/{y}.pbf', route('gis.tiles', ['layer' => $layer->id, 'z' => 0, 'x' => 0, 'y' => 0])),
            ])->values()->all();

        $imagery = [];
        foreach ((array) config('gis.imagery') as $key => $source) {
            if (($source['url'] ?? '') === '') {
                continue;
            }
            $imagery[] = ['key' => $key, 'label' => $source['label'], 'url' => $source['url'], 'attribution' => $source['attribution']];
        }

        $focus = $request->query('item');

        return view('osc.map', ['meeting' => $meeting, 'config' => [
            'mapDataUrl' => route('osc.meetings.map-data', $meeting),
            'layers' => $layers,
            'imagery' => $imagery,
            'regionBbox' => config('gis.region_bbox'),
            'focusItemId' => is_string($focus) && Str::isUlid($focus) ? $focus : null,
            'labels' => self::LABELS,
        ]]);
    }

    public function data(Request $request, Meeting $meeting, MeetingMapService $map): JsonResponse
    {
        $user = $request->user();
        abort_unless(OscAccess::canViewMeeting($user, $meeting), 403);

        return response()->json($map->forMeeting($meeting, $user), 200, ['Cache-Control' => 'no-store']);
    }
}
