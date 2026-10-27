<?php

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PkmRegistrationService
{
    public function __construct(private readonly PkmReferenceGenerator $referenceGenerator) {}

    public function register(array $data, User $actor): Application
    {
        return DB::transaction(function () use ($data, $actor): Application {
            $submissionDate = CarbonImmutable::parse($data['submission_date']);

            $application = Application::query()->create([
                'application_type_id' => 'PKM',
                'application_reference' => $this->referenceGenerator->next($submissionDate),
                'external_reference' => $data['external_reference'] ?? null,
                'title' => $data['application_title'],
                'description' => $data['application_description'] ?? null,
                'submission_date' => $submissionDate,
                'submission_year' => (int) $submissionDate->format('Y'),
                'responsible_department_id' => 'JPBD',
                'current_stage' => ApplicationStage::Registration,
                'current_status' => ApplicationStatus::Submitted,
                'priority' => $data['priority'],
            ]);

            $application->pkm()->create(Arr::only($data['pkm'], [
                'permission_category',
                'permission_type',
                'existing_land_use',
                'proposed_land_use',
                'density',
                'plot_ratio',
                'number_of_units',
                'number_of_storeys',
                'building_height',
                'planning_standards',
            ]));

            $this->storeParty($application, $data['applicant'], 'applicant', true);

            if (filled($data['consultant']['name'] ?? null)) {
                $this->storeParty(
                    $application,
                    $data['consultant'],
                    $data['consultant']['role'] ?? 'consultant',
                    false,
                );
            }

            $site = Site::query()->create(Arr::only($data['site'], [
                'development_address',
                'district',
                'mukim',
                'state',
                'site_area',
                'site_area_unit',
            ]));

            $application->sites()->attach($site->id, ['is_primary' => true]);

            foreach ($data['lots'] as $lotData) {
                $lot = Lot::query()->create([
                    ...Arr::only($lotData, [
                        'lot_number',
                        'lot_type',
                        'section',
                        'land_area',
                        'land_area_unit',
                    ]),
                    'mukim' => $lotData['mukim'] ?? $site->mukim,
                    'district' => $lotData['district'] ?? $site->district,
                    'state' => $lotData['state'] ?? $site->state,
                ]);

                $application->lots()->attach($lot->id);
            }

            foreach ($data['components'] ?? [] as $component) {
                $application->pkm->components()->create(Arr::only($component, [
                    'name',
                    'category',
                    'number_of_units',
                    'floor_area',
                    'notes',
                ]));
            }

            $application->stageHistory()->create([
                'new_stage' => ApplicationStage::Registration,
                'new_status' => ApplicationStatus::Submitted,
                'action' => 'application_registered',
                'actor_id' => $actor->id,
                'remarks' => 'Permohonan PKM didaftarkan dalam iNDECS.',
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

            return $application->load([
                'pkm.components',
                'applicationParties.party',
                'sites',
                'lots',
                'stageHistory',
            ]);
        });
    }

    private function storeParty(
        Application $application,
        array $partyData,
        string $role,
        bool $isPrimary,
    ): void {
        $snapshot = Arr::only($partyData, [
            'party_type',
            'name',
            'registration_number',
            'email',
            'phone',
            'address',
        ]);

        $party = filled($snapshot['registration_number'] ?? null)
            ? Party::query()->firstOrCreate(
                ['registration_number' => $snapshot['registration_number']],
                $snapshot,
            )
            : Party::query()->create($snapshot);

        $application->applicationParties()->create([
            'party_id' => $party->id,
            'role' => $role,
            'is_primary' => $isPrimary,
            'submitted_snapshot' => $snapshot,
        ]);
    }
}
