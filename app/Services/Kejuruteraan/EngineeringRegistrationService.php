<?php

declare(strict_types=1);

namespace App\Services\Kejuruteraan;

use App\Http\Requests\StoreEngineeringApplicationRequest;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationRegistrationService;
use App\Support\DepartmentStructure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class EngineeringRegistrationService
{
    public function register(array $data, User $actor): Application
    {
        abort_if(app(EngineeringApplicationAccess::class)->types($actor, 'register') === [], 403);
        $data = Validator::make($data, StoreEngineeringApplicationRequest::registrationRules($actor))->validate();

        return DB::transaction(function () use ($data, $actor): Application {
            $application = app(ApplicationRegistrationService::class)->register($data, $actor, $data['application_type_id'], 'JKEJ',
                'kejuruteraan.application.access', 'kejuruteraan.application.register');
            $updated = DB::table(DepartmentStructure::applicationTable($application->application_type_id))
                ->where('application_id', $application->id)->update(['technical_notes' => $data['engineering']['technical_notes'] ?? null, 'updated_at' => now()]);
            abort_unless($updated === 1, 409, 'Butiran jabatan tidak dapat disimpan.');

            return $application;
        });
    }
}
