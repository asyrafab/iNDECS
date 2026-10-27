<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoreRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_perancang_permission_grants_existing_pkm_access(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();

        $this->assertTrue($user->canAccessPkm());
        $this->assertTrue($user->canPerformPkm('perancang.pkm.register'));
        $this->assertTrue($user->canPerformPkm('perancang.pkm.review'));
        $this->assertDatabaseHas('core.role_permissions', [
            'role_id' => 'planning_officer',
            'permission_name' => 'perancang.pkm.access',
            'department_id' => 'JPBD',
            'application_type_id' => 'PKM',
        ]);
    }

    public function test_legacy_role_string_without_permission_grant_is_denied(): void
    {
        $this->seed();
        DB::table('core.role_permissions')->delete();

        $user = User::query()->firstOrFail();

        $this->assertFalse($user->canAccessPkm());
        $this->actingAs($user)->get(route('perancang.index'))->assertForbidden();
    }

    public function test_department_scope_applies_except_for_global_system_admin_grant(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->forceFill(['department_id' => 'JBANG'])->save();

        $this->assertFalse($user->canAccessPkm());

        $user->forceFill(['role' => 'system_admin'])->save();

        $this->assertTrue($user->fresh()->canAccessPkm());
    }

    public function test_intake_officer_can_register_but_cannot_execute_completeness_review_actions(): void
    {
        $this->seed();

        $intakeOfficer = User::factory()->create([
            'department_id' => 'JPBD',
            'role' => 'intake_officer',
            'is_active' => true,
        ]);

        $this->assertTrue($intakeOfficer->canAccessPkm());
        $this->assertTrue($intakeOfficer->canPerformPkm('perancang.pkm.register'));
        $this->assertFalse($intakeOfficer->canPerformPkm('perancang.pkm.review'));

        $this->actingAs($intakeOfficer)
            ->post(route('perancang.store'), $this->validPayload('Seberang Perai Tengah'))
            ->assertRedirect();

        $application = Application::query()->firstOrFail();

        $this->actingAs($intakeOfficer)
            ->post(route('perancang.start-review', $application))
            ->assertForbidden();
    }

    public function test_district_scoped_officer_can_only_view_and_act_on_assigned_district(): void
    {
        $this->seed();

        $sptOfficer = User::factory()->create([
            'department_id' => 'JPBD',
            'district' => 'Seberang Perai Tengah',
            'role' => 'planning_officer',
            'is_active' => true,
        ]);

        $spuOfficer = User::factory()->create([
            'department_id' => 'JPBD',
            'district' => 'Seberang Perai Utara',
            'role' => 'planning_officer',
            'is_active' => true,
        ]);

        $this->actingAs($sptOfficer)
            ->post(route('perancang.store'), $this->validPayload('Seberang Perai Tengah'))
            ->assertRedirect();

        $sptApplication = Application::query()->firstOrFail();

        $this->assertTrue($sptOfficer->canAccessApplication($sptApplication));
        $this->assertFalse($spuOfficer->canAccessApplication($sptApplication));

        $this->actingAs($sptOfficer)
            ->get(route('perancang.show', $sptApplication))
            ->assertOk();

        $this->actingAs($spuOfficer)
            ->get(route('perancang.show', $sptApplication))
            ->assertForbidden();

        $this->actingAs($spuOfficer)
            ->post(route('perancang.start-review', $sptApplication))
            ->assertForbidden();

        $this->actingAs($spuOfficer)
            ->get(route('perancang.index'))
            ->assertOk()
            ->assertDontSee($sptApplication->application_reference);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $district): array
    {
        return [
            'application_title' => 'Cadangan Perumahan Daerah',
            'application_description' => 'Cadangan 120 unit kediaman.',
            'external_reference' => 'OSC/2026/0200',
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
                'development_address' => 'Lot 101, Mukim 6',
                'district' => $district,
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
                    'land_area' => '4.2500',
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
