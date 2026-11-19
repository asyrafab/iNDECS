<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEngineeringApplicationRequest;
use App\Models\ApplicationType;
use App\Services\Kejuruteraan\EngineeringApplicationAccess;
use App\Services\Kejuruteraan\EngineeringRegistrationService;
use Illuminate\Http\Request;

final class EngineeringRegistrationController extends Controller
{
    public function create(Request $request)
    {
        $types = ApplicationType::query()->whereIn('id', app(EngineeringApplicationAccess::class)->types($request->user(), 'register'))
            ->where('primary_department_id', 'JKEJ')->where('is_active', true)->orderBy('id')->get();
        abort_if($types->isEmpty(), 403);
        $districts = $request->user()->role !== 'system_admin' && filled($request->user()->district) ? [$request->user()->district] : EngineeringApplicationAccess::DISTRICTS;

        return view('kejuruteraan.create', compact('types', 'districts'));
    }

    public function store(StoreEngineeringApplicationRequest $request, EngineeringRegistrationService $service)
    {
        $application = $service->register($request->validated(), $request->user());

        return to_route('kejuruteraan.show', $application)->with('success', 'Permohonan '.$application->application_reference.' berjaya didaftarkan.');
    }
}
