<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\StageHistory;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppendOnlyHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->officer = User::query()->firstOrFail();
        $this->actingAs($this->officer);

        $this->post(route('perancang.store'), $this->validPayload())->assertRedirect();
        $this->application = Application::query()->firstOrFail();
    }

    public function test_audit_logs_reject_eloquent_and_raw_sql_updates_and_deletes(): void
    {
        $audit = AuditLog::query()->firstOrFail();

        try {
            $audit->update(['event' => 'tampered']);
            $this->fail('Expected AuditLog Eloquent update to be rejected.');
        } catch (DomainException) {
            $this->assertSame('created', $audit->fresh()->event);
        }

        try {
            $audit->delete();
            $this->fail('Expected AuditLog Eloquent delete to be rejected.');
        } catch (DomainException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $audit->id]);
        }

        try {
            DB::transaction(fn () => DB::table('audit_logs')->where('id', $audit->id)->update(['event' => 'tampered']));
            $this->fail('Expected PostgreSQL trigger to reject raw audit_logs UPDATE.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            DB::transaction(fn () => DB::table('audit_logs')->where('id', $audit->id)->delete());
            $this->fail('Expected PostgreSQL trigger to reject raw audit_logs DELETE.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_stage_histories_reject_eloquent_and_raw_sql_updates_and_deletes(): void
    {
        $history = StageHistory::query()->firstOrFail();

        try {
            $history->update(['action' => 'tampered']);
            $this->fail('Expected StageHistory Eloquent update to be rejected.');
        } catch (DomainException) {
            $this->assertSame('application_registered', $history->fresh()->action);
        }

        try {
            $history->delete();
            $this->fail('Expected StageHistory Eloquent delete to be rejected.');
        } catch (DomainException) {
            $this->assertDatabaseHas('stage_histories', ['id' => $history->id]);
        }

        try {
            DB::transaction(fn () => DB::table('stage_histories')->where('id', $history->id)->update(['action' => 'tampered']));
            $this->fail('Expected PostgreSQL trigger to reject raw stage_histories UPDATE.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            DB::transaction(fn () => DB::table('stage_histories')->where('id', $history->id)->delete());
            $this->fail('Expected PostgreSQL trigger to reject raw stage_histories DELETE.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_referenced_application_and_actor_user_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);
        DB::table('applications')->where('id', $this->application->id)->delete();
    }

    public function test_referenced_actor_user_cannot_be_deleted_to_null_history_attribution(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $this->officer->id)->delete();
    }

    /**
     * @return array<string, mixed>
     */
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
