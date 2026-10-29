<?php

declare(strict_types=1);

namespace App\Http\Controllers\Osc;

use App\Http\Controllers\Controller;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\LayoutImage;
use App\Services\Gis\MapConfig;
use App\Services\Osc\LayoutImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LayoutImageController extends Controller
{
    /** BM prompts for the control-points editor. */
    private const MESSAGES = [
        'choose' => 'Pilih fail imej pelan untuk bermula.',
        'pickImage' => 'Klik titik :n pada imej.',
        'pickMap' => 'Klik titik :n yang sepadan pada peta.',
        'pickImageFirst' => 'Klik titik pada imej dahulu, kemudian titik yang sepadan pada peta.',
        'ready' => 'Semak pratonton pada peta. Pilih Simpan kedudukan, atau Ulang titik untuk memulakan semula.',
        'invalid' => 'Titik kawalan tidak sah. Pilih tiga titik yang tidak sebaris dan ulang.',
        'unreadable' => 'Imej tidak dapat dibaca. Pilih fail JPG atau PNG yang lain.',
    ];

    public function edit(Request $request, AgendaItem $item): View
    {
        abort_unless(LayoutImageService::canPlace($request->user(), $item), 403);
        $current = LayoutImage::query()->where('owner_type', 'agenda_item')->where('owner_id', $item->id)->first();
        $item->load('meeting');

        return view('osc.layout', [
            'item' => $item, 'meeting' => $item->meeting, 'current' => $current,
            'config' => MapConfig::forBoundaryField() + ['messages' => self::MESSAGES],
        ]);
    }

    public function store(Request $request, AgendaItem $item, LayoutImageService $layouts): RedirectResponse
    {
        abort_unless(LayoutImageService::canPlace($request->user(), $item), 403);
        $data = $request->validate([
            'layout' => ['required', 'file'],
            'control_points' => ['required', 'array'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
        ], ['layout.required' => 'Pilih fail imej pelan (JPG atau PNG).', 'control_points.required' => 'Tandakan tiga titik kawalan pada imej dan peta.']);
        $layouts->place($item, $request->file('layout'), $data['control_points'], isset($data['expected_version']) ? (int) $data['expected_version'] : null, $request->user());

        return to_route('osc.meetings.map', ['meeting' => $item->meeting_id, 'item' => $item->id])->with('status', 'Kedudukan pelan telah disimpan.');
    }

    public function show(Request $request, LayoutImage $layoutImage, LayoutImageService $layouts): StreamedResponse
    {
        return $layouts->show($layoutImage, $request->user());
    }
}
