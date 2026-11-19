<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Models\Application;
use App\Models\User;
use App\Services\Kejuruteraan\EngineeringRegistrationService;
use App\Services\Tasks\OfficerTaskAccess;
use Database\Seeders\EngineeringRegistrationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EngineeringRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function officer(array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => 'engineering_officer', 'department_id' => 'JKEJ', 'district' => 'Seberang Perai Tengah']);
    }

    private function payload(string $type = 'PKT'): array
    {
        return ['application_type_id' => $type, 'application_title' => 'Cadangan Kejuruteraan '.$type,
            'submission_date' => today()->toDateString(), 'priority' => 'normal',
            'applicant' => ['party_type' => 'organisation', 'name' => 'Pemohon Ujian', 'registration_number' => 'ENG-123'],
            'site' => ['development_address' => 'Jalan Ujian', 'district' => 'Seberang Perai Tengah', 'mukim' => 'Mukim 1'],
            'lots' => [['lot_number' => '101']], 'engineering' => ['technical_notes' => 'Catatan asal pegawai']];
    }

    public static function types(): array
    {
        return [['PKT', 'app_pkt_applications'], ['PKP', 'app_pkp_applications']];
    }

    #[DataProvider('types')]
    public function test_officer_can_register_and_find_each_type_with_history(string $type, string $table): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->get(route('kejuruteraan.create'))->assertOk()->assertSee('PKT')->assertSee('PKP');
        $this->post(route('kejuruteraan.store'), $this->payload($type) + ['current_status' => 'approved', 'assigned_to' => $officer->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $application = Application::query()->sole();
        $this->assertMatchesRegularExpression('#^'.$type.'/\d{4}/00001$#', $application->application_reference);
        $this->assertSame('registration', $application->current_stage->value);
        $this->assertSame('submitted', $application->current_status->value);
        $this->assertNull($application->assigned_to);
        $this->assertDatabaseHas('kejuruteraan.'.$table, ['application_id' => $application->id, 'technical_notes' => 'Catatan asal pegawai']);
        $this->assertDatabaseHas('core.application_parties', ['application_id' => $application->id, 'role' => 'applicant']);
        $this->assertDatabaseCount('core.stage_histories', 1);
        $this->assertDatabaseHas('core.audit_logs', ['auditable_id' => $application->id, 'event' => 'created', 'actor_id' => $officer->id]);
        $this->get(route('kejuruteraan.index'))->assertOk()->assertSee($application->application_reference);
        $this->get(route('kejuruteraan.dashboard'))->assertOk()->assertSee($application->application_reference);
        $this->get(route('kejuruteraan.show', $application))->assertOk()->assertSee('Catatan asal pegawai')->assertSee('Pemohon Ujian');
        $this->assertTrue(app(OfficerTaskAccess::class)->applications($officer)->whereKey($application->id)->exists());
        $senior = $this->officer(['role' => 'senior_engineering_officer']);
        $this->assertTrue(app(OfficerTaskAccess::class)->canAssign($senior, $application));
        $this->assertFalse(app(OfficerTaskAccess::class)->canAssign($officer, $application));
    }

    public function test_district_and_permission_scopes_apply_to_records_and_forms(): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->post(route('kejuruteraan.store'), array_replace_recursive($this->payload(), ['site' => ['district' => 'Seberang Perai Utara']]))
            ->assertSessionHasErrors('site.district');
        $application = app(EngineeringRegistrationService::class)->register($this->payload(), $officer);
        $foreign = $this->officer(['district' => 'Seberang Perai Utara']);
        $this->actingAs($foreign)->get(route('kejuruteraan.index'))->assertOk()->assertDontSee($application->application_reference);
        $this->get(route('kejuruteraan.show', $application))->assertNotFound();
        DB::table('core.role_permissions')->where('role_id', 'engineering_officer')->where('permission_name', 'kejuruteraan.application.register')->delete();
        $this->actingAs($officer)->get(route('kejuruteraan.create'))->assertForbidden();
        $this->post(route('kejuruteraan.store'), $this->payload())->assertForbidden();
        $this->get(route('kejuruteraan.show', $application))->assertOk();
    }

    public function test_ljup_and_other_departments_cannot_use_this_registration_path(): void
    {
        $officer = $this->officer();
        foreach (['LJUP', 'PKM', 'PB', 'PL'] as $type) {
            $this->actingAs($officer)->post(route('kejuruteraan.store'), $this->payload($type))->assertSessionHasErrors('application_type_id');
        }
        foreach ([['role' => 'planning_officer', 'department_id' => 'JPBD'], ['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null], ['is_active' => false]] as $attributes) {
            $user = $this->officer($attributes);
            $this->actingAs($user)->get(route('kejuruteraan.create'))->assertForbidden();
            $this->post(route('kejuruteraan.store'), $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('core.applications', 0);
        $this->assertDatabaseCount('core.paid_check_journeys', 0);
    }

    public function test_native_access_does_not_grant_ljup_record_access(): void
    {
        $officer = $this->officer();
        $ljup = Application::query()->create(['application_type_id' => 'LJUP', 'application_reference' => 'LJUP/2026/09999', 'title' => 'Private LJUP',
            'submission_date' => today(), 'submission_year' => 2026, 'responsible_department_id' => 'JKEJ', 'current_stage' => 'registration', 'current_status' => 'submitted']);
        DB::table('core.role_permissions')->where('role_id', $officer->role)->where('permission_name', 'like', 'kejuruteraan.ljup.%')->delete();
        $this->actingAs($officer)->get(route('kejuruteraan.create'))->assertOk();
        $this->get(route('kejuruteraan.index'))->assertOk()->assertDontSee($ljup->application_reference);
        $this->get(route('kejuruteraan.show', $ljup))->assertForbidden();
    }

    public function test_failed_workflow_validation_leaves_no_partial_records_or_reference(): void
    {
        $officer = $this->officer();
        DB::table('core.workflow_templates')->where('application_type_id', 'PKT')->update(['is_active' => false]);
        $this->actingAs($officer)->post(route('kejuruteraan.store'), $this->payload())->assertStatus(409);
        foreach (['core.applications', 'core.reference_sequences', 'core.parties', 'core.sites', 'core.lots', 'core.stage_histories'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_seeding_is_idempotent_and_type_scopes_are_enforced(): void
    {
        $count = DB::table('core.role_permissions')->count();
        $this->seed(EngineeringRegistrationSeeder::class);
        $this->assertDatabaseCount('core.role_permissions', $count);
        $officer = $this->officer();
        DB::table('core.role_permissions')->where('role_id', $officer->role)->where('permission_name', 'kejuruteraan.application.access')->where('application_type_id', 'PKP')->delete();
        $this->actingAs($officer)->get(route('kejuruteraan.create'))->assertOk()->assertViewHas('types', fn ($types) => $types->pluck('id')->all() === ['PKT']);
        $this->post(route('kejuruteraan.store'), $this->payload('PKP'))->assertForbidden();
        DB::table('core.application_types')->where('id', 'PKT')->update(['is_active' => false]);
        $this->post(route('kejuruteraan.store'), $this->payload())->assertSessionHasErrors('application_type_id');
    }

    public function test_failure_after_common_records_are_written_rolls_back_the_entire_registration(): void
    {
        $officer = $this->officer();
        DB::unprepared("CREATE FUNCTION pg_temp.reject_engineering_notes() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'engineering notes rejected'; END; $$;
            CREATE TRIGGER reject_engineering_notes BEFORE UPDATE ON kejuruteraan.app_pkt_applications FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_engineering_notes();");
        try {
            app(EngineeringRegistrationService::class)->register($this->payload(), $officer);
            $this->fail('Expected the extension write to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('engineering notes rejected', $exception->getMessage());
        }
        foreach (['core.applications', 'core.reference_sequences', 'core.parties', 'core.sites', 'core.lots', 'core.stage_histories', 'core.audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_migration_rolls_back_and_reapplies_before_records_exist(): void
    {
        $migration = require database_path('migrations/2026_11_19_010000_enable_engineering_officer_registration.php');
        $migration->down();
        $this->assertDatabaseHas('core.application_types', ['id' => 'PKT', 'is_active' => false]);
        $migration->up();
        $this->assertDatabaseHas('core.application_types', ['id' => 'PKT', 'is_active' => true]);
        $this->assertTrue($this->officer()->canPerformEngineering('register', 'PKP'));
    }

    public function test_migration_rollback_preserves_registered_records(): void
    {
        $application = app(EngineeringRegistrationService::class)->register($this->payload(), $this->officer());
        $migration = require database_path('migrations/2026_11_19_010000_enable_engineering_officer_registration.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse to disable registered applications.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Engineering registrations exist', $exception->getMessage());
        }
        $this->assertDatabaseHas('core.applications', ['id' => $application->id]);
        $this->assertDatabaseHas('core.application_types', ['id' => 'PKT', 'is_active' => true]);
    }
}
