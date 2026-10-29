<?php

declare(strict_types=1);

namespace App\Http\Controllers\Osc;

use App\Http\Controllers\Controller;
use App\Jobs\Gis\ReplicateLayers;
use App\Services\Osc\OscAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class GisRefreshController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(OscAccess::canManage($request->user()), 403);

        ReplicateLayers::dispatch($request->user());

        return back()->with('status', 'Kemas kini lapisan dimulakan.');
    }
}
