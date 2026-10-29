<?php

declare(strict_types=1);

namespace App\Http\Controllers\Osc;

use App\Http\Controllers\Controller;
use App\Models\Osc\Meeting;
use App\Services\Osc\MeetingMapService;
use App\Services\Osc\OscAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeetingMapController extends Controller
{
    public function data(Request $request, Meeting $meeting, MeetingMapService $map): JsonResponse
    {
        $user = $request->user();
        abort_unless(OscAccess::canViewMeeting($user, $meeting), 403);

        return response()->json($map->forMeeting($meeting, $user), 200, ['Cache-Control' => 'no-store']);
    }
}
