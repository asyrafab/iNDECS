<?php

namespace Tests\Feature;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PkmRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->actingAs(User::query()->firstOrFail());
    }

    public function test_an_officer_can_register_a_complete_pkm_application(): void
    {
        $response = $this->post(route('pkm.store'), $this->validPayload());

        $application = Application::query()->firstOrFail();

        $response->assertRedirect(route('pkm.show', $application));
        $this->assertSame('PKM/2026/00001', $application->application_reference);
        $this->assertSame(ApplicationStage::Registration, $application->current_stage);
        $this->assertSame(ApplicationStatus::Submitted, $application->current_status);

        $this->assertDatabaseHas('pkm_applications', [
            'application_id' => $application->id,
            'permission_category' => 'Pembangunan Baharu',
            'proposed_land_use' => 'Perumahan',
        ]);
        $this->assertDatabaseCount('application_parties', 2);
        $this->assertDatabaseCount('application_sites', 1);
        $this->assertDatabaseCount('application_lots', 2);
        $this->assertDatabaseCount('pkm_components', 1);
        $this->assertDatabaseCount('stage_histories', 1);
        $this->assertDatabaseCount('audit_logs', 1);

        $snapshot = ApplicationParty::query()->where('role', 'applicant')->firstOrFail()->submitted_snapshot;
        $this->assertSame('Pemaju Seberang Sdn. Bhd.', $snapshot['name']);
        $this->assertSame('202601234567', $snapshot['registration_number']);
    }

    public function test_reference_numbers_are_sequential_within_the_submission_year(): void
    {
        $this->post(route('pkm.store'), $this->validPayload())->assertRedirect();

        $secondPayload = $this->validPayload();
        $secondPayload['application_title'] = 'Cadangan Kedua';
        $this->post(route('pkm.store'), $secondPayload)->assertRedirect();

        $this->assertSame(
            ['PKM/2026/00001', 'PKM/2026/00002'],
            Application::query()->orderBy('application_reference')->pluck('application_reference')->all(),
        );
        $this->assertDatabaseCount('parties', 2);
    }

    public function test_a_submitted_application_can_enter_completeness_review_once(): void
    {
        $this->post(route('pkm.store'), $this->validPayload());
        $application = Application::query()->firstOrFail();

        $this->post(route('pkm.start-review', $application))
            ->assertRedirect()
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame(ApplicationStage::CompletenessCheck, $application->current_stage);
        $this->assertSame(ApplicationStatus::InReview, $application->current_status);
        $this->assertSame(2, $application->row_version);
        $this->assertDatabaseCount('stage_histories', 2);
        $this->assertDatabaseCount('audit_logs', 2);

        $this->post(route('pkm.start-review', $application))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('stage_histories', 2);
    }

    public function test_registration_requires_at_least_one_cadastral_lot(): void
    {
        $payload = $this->validPayload();
        $payload['lots'] = [];

        $this->post(route('pkm.store'), $payload)
            ->assertSessionHasErrors('lots');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_registered_application_is_visible_in_the_workspace(): void
    {
        $this->post(route('pkm.store'), $this->validPayload());
        $application = Application::query()->firstOrFail();

        $this->get(route('pkm.index'))
            ->assertOk()
            ->assertSee($application->application_reference)
            ->assertSee('Cadangan Perumahan Bersepadu');

        $this->get(route('pkm.show', $application))
            ->assertOk()
            ->assertSee('Pemaju Seberang Sdn. Bhd.')
            ->assertSee('Lot 101');
    }

    private function validPayload(): array
    {
        return [
            'application_title' => 'Cadangan Perumahan Bersepadu',
            'application_description' => 'Cadangan 120 unit kediaman berserta kemudahan.',
            'external_reference' => 'OSC/2026/0142',
            'submission_date' => '2026-07-14',
            'priority' => 'normal',
            'applicant' => [
                'party_type' => 'organisation',
                'name' => 'Pemaju Seberang Sdn. Bhd.',
                'registration_number' => '202601234567',
                'email' => 'projek@example.test',
                'phone' => '04-555 0123',
                'address' => 'Perai, Pulau Pinang',
            ],
            'consultant' => [
                'party_type' => 'organisation',
                'role' => 'town_planner',
                'name' => 'Rancang Utara PLT',
                'registration_number' => 'LPBM-999',
                'email' => 'planner@example.test',
                'phone' => '04-555 0456',
            ],
            'site' => [
                'development_address' => 'Lot 101 dan Lot 102, Mukim 6',
                'district' => 'Seberang Perai Tengah',
                'mukim' => 'Mukim 6',
                'state' => 'Pulau Pinang',
                'site_area' => '4.2500',
                'site_area_unit' => 'hectare',
            ],
            'lots' => [
                [
                    'lot_number' => 'Lot 101',
                    'lot_type' => 'Lot',
                    'section' => null,
                    'land_area' => '2.1000',
                    'land_area_unit' => 'hectare',
                ],
                [
                    'lot_number' => 'Lot 102',
                    'lot_type' => 'Lot',
                    'section' => null,
                    'land_area' => '2.1500',
                    'land_area_unit' => 'hectare',
                ],
            ],
            'pkm' => [
                'permission_category' => 'Pembangunan Baharu',
                'permission_type' => 'Kebenaran Merancang Penuh',
                'existing_land_use' => 'Pertanian',
                'proposed_land_use' => 'Perumahan',
                'density' => '28.24',
                'plot_ratio' => '1.250',
                'number_of_units' => 120,
                'number_of_storeys' => 3,
                'building_height' => '12.50',
                'planning_standards' => 'Tertakluk kepada semakan teknikal.',
            ],
            'components' => [
                [
                    'name' => 'Rumah Teres Dua Tingkat',
                    'category' => 'Kediaman',
                    'number_of_units' => 120,
                    'floor_area' => '18200.00',
                ],
            ],
        ];
    }
}
