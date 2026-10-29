<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gis;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Gis\BoundaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BoundaryController extends Controller
{
    public function store(Request $request, Application $application, BoundaryService $boundaries): RedirectResponse
    {
        abort_unless($boundaries->canSet($request->user(), $application), 403);
        $data = $request->validate([
            'boundary' => ['required', 'string'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ], ['boundary.required' => 'Lukis sempadan tapak terlebih dahulu.']);
        $boundaries->storeForApplication($application, $data['boundary'], (int) $data['expected_version'], $request->user());

        return back()->with('success', 'Sempadan tapak telah disimpan.');
    }
}
