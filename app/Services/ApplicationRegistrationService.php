<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationType;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Services\Gis\BoundaryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class ApplicationRegistrationService
{
    public function __construct(private readonly ApplicationReferenceGenerator $references, private readonly BoundaryService $boundaries) {}

    public function register(array $data, User $actor, string $typeId, string $department, string $accessPermission, string $registrationPermission): Application
    {
        $permissions = app(CorePermissionService::class);
        abort_unless($permissions->userCan($actor, $accessPermission, $actor->department_id, $typeId)
            && $permissions->userCan($actor, $registrationPermission, $actor->department_id, $typeId), 403);

        return DB::transaction(function () use ($data, $actor, $typeId, $department, $registrationPermission): Application {
            $boundary = filled($data['site']['boundary'] ?? null) ? $this->boundaries->normalise($data['site']['boundary'], 'site.boundary') : null;
            $type = ApplicationType::query()->whereKey($typeId)
                ->where('primary_department_id', $department)->where('is_active', true)->sharedLock()->firstOrFail();
            $template = DB::table('core.workflow_templates')->where('application_type_id', $type->id)
                ->where('is_active', true)->orderByDesc('version')->sharedLock()->first();
            abort_unless($template && $template->registration_permission === $registrationPermission, 409, 'Templat aliran kerja belum tersedia.');
            abort_unless(app(CorePermissionService::class)->userCan($actor, $template->registration_permission, $actor->department_id, $type->id), 403);
            $date = CarbonImmutable::parse($data['submission_date']);
            $application = Application::query()->create([
                'application_type_id' => $type->id,
                'application_reference' => $this->references->next($type->id, $date),
                'external_reference' => $data['external_reference'] ?? null,
                'title' => $data['application_title'],
                'description' => $data['application_description'] ?? null,
                'submission_date' => $date,
                'submission_year' => $date->year,
                'responsible_department_id' => $type->primary_department_id,
                'workflow_template_id' => $template->id,
                'current_stage' => ApplicationStage::from($template->initial_stage),
                'current_status' => ApplicationStatus::from($template->initial_status),
                'priority' => $data['priority'],
                'row_version' => 1,
            ]);

            foreach (['applicant', 'consultant'] as $role) {
                if (blank($data[$role]['name'] ?? null)) {
                    continue;
                }
                $snapshot = Arr::only($data[$role], ['party_type', 'name', 'registration_number', 'email', 'phone', 'address']);
                $party = filled($snapshot['registration_number'] ?? null)
                    ? Party::query()->firstOrCreate(['registration_number' => $snapshot['registration_number']], $snapshot)
                    : Party::query()->create($snapshot);
                $application->applicationParties()->create([
                    'party_id' => $party->id,
                    'role' => $role,
                    'is_primary' => $role === 'applicant',
                    'submitted_snapshot' => $snapshot,
                ]);
            }

            $site = Site::query()->create(Arr::except($data['site'], 'boundary') + ['state' => 'Pulau Pinang']);
            if ($boundary !== null) {
                DB::update('UPDATE sites SET boundary = ?::geometry WHERE id = ?', [$boundary, $site->id]);
            }
            $application->sites()->attach($site->id, ['is_primary' => true]);

            foreach ($data['lots'] as $lot) {
                $record = Lot::query()->create($lot + ['district' => $site->district, 'mukim' => $site->mukim, 'state' => $site->state]);
                $application->lots()->attach($record->id);
            }

            $application->stageHistory()->create([
                'new_stage' => $application->current_stage,
                'new_status' => $application->current_status,
                'action' => 'application_registered',
                'actor_id' => $actor->id,
                'remarks' => 'Permohonan '.$typeId.' didaftarkan.',
            ]);

            AuditLog::query()->create([
                'auditable_type' => Application::class,
                'auditable_id' => $application->id,
                'event' => 'created',
                'actor_id' => $actor->id,
                'after_values' => [
                    'application_reference' => $application->application_reference,
                    'current_stage' => $application->current_stage->value,
                    'current_status' => $application->current_status->value,
                ],
                'ip_address' => request()?->ip(),
            ]);

            return $application;
        });
    }
}
