<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Site;
use App\Models\User;
use App\Services\ApplicationLauncher;
use App\Services\Tasks\OfficerTaskService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DepartmentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public static function departments(): array
    {
        return [
            ['perancang', 'JPBD', 'PKM', 'planning_officer'],
            ['bangunan', 'JBANG', 'PB', 'building_officer'],
            ['kejuruteraan', 'JKEJ', 'LJUP', 'engineering_officer'],
            ['landskap', 'JLAND', 'PL', 'landscape_officer'],
        ];
    }

    private function record(string $type, string $department, ?string $district = null): Application
    {
        $application = Application::query()->create(['application_type_id' => $type, 'application_reference' => $type.'/'.Str::ulid(),
            'title' => 'Cadangan '.$type, 'submission_date' => today()->toDateString(), 'submission_year' => 2026,
            'responsible_department_id' => $department, 'current_stage' => 'registration', 'current_status' => 'submitted']);
        if ($district) {
            $site = Site::query()->create(['development_address' => 'Jalan Ujian', 'district' => $district, 'mukim' => 'Mukim 1']);
            $application->sites()->attach($site->id);
        }

        return $application;
    }

    #[DataProvider('departments')]
    public function test_each_dashboard_scopes_records_and_links_without_writes(string $department, string $departmentId, string $type, string $role): void
    {
        $actor = User::factory()->create(['role' => $role, 'department_id' => $departmentId, 'district' => 'Seberang Perai Tengah']);
        $visible = $this->record($type, $departmentId, $actor->district);
        $hidden = $this->record($type, $departmentId, 'Seberang Perai Utara');
        $before = $visible->fresh()->getAttributes();
        $audits = DB::table('audit_logs')->count();
        $response = $this->actingAs($actor)->get(route($department.'.dashboard'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('metrics', fn ($metrics) => (int) $metrics['total'] === 1 && (int) $metrics['submitted'] === 1)
            ->assertSee($visible->application_reference)->assertDontSee($hidden->application_reference)
            ->assertSee('href="'.route($department.'.show', $visible).'"', false)
            ->assertSee('href="'.route($department.'.index').'"', false);
        $this->assertSame($before, $visible->fresh()->getAttributes());
        $this->assertSame($audits, DB::table('audit_logs')->count());
        $entry = collect(app(ApplicationLauncher::class)->forUser($actor))->firstWhere('department', $department);
        $this->assertSame(route($department.'.dashboard'), $entry['url']);
        $this->assertSame(route($department === 'perancang' ? 'dashboard' : $department.'.dashboard'), $actor->staffHomeRoute());
        $this->get(route('login'))->assertRedirect(route($department === 'perancang' ? 'dashboard' : $department.'.dashboard'));
        $this->capture($department, $response->getContent());
    }

    public function test_ljup_unlocated_records_remain_visible_and_engineering_landing_works(): void
    {
        $officer = User::factory()->create(['role' => 'engineering_officer', 'department_id' => 'JKEJ', 'district' => 'Seberang Perai Tengah']);
        $record = $this->record('LJUP', 'JKEJ');
        $this->actingAs($officer)->get(route('dashboard'))->assertRedirect(route('kejuruteraan.dashboard'));
        $this->get(route('kejuruteraan.dashboard'))->assertOk()->assertSee($record->application_reference)->assertSee('PKT dan PKP belum tersedia');
    }

    public function test_bangunan_totals_exclude_unpermitted_families(): void
    {
        $officer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG', 'district' => null]);
        DB::table('core.role_permissions')->where('role_id', 'building_officer')->where('permission_name', 'bangunan.application.access')->update(['application_type_id' => 'TEL']);
        $visible = $this->record('TEL', 'JBANG');
        $hidden = $this->record('PB', 'JBANG');
        $this->actingAs($officer)->get(route('bangunan.dashboard'))->assertOk()
            ->assertViewHas('metrics', fn ($metrics) => (int) $metrics['total'] === 1)
            ->assertSee($visible->application_reference)->assertDontSee($hidden->application_reference);
    }

    public function test_task_summaries_use_existing_own_team_and_deadline_rules(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 17:00:00', 'UTC'));
        try {
            $senior = User::factory()->create(['role' => 'senior_planning_officer', 'department_id' => 'JPBD', 'district' => null]);
            $officer = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD', 'district' => null]);
            $other = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD', 'district' => null]);
            $record = $this->record('PKM', 'JPBD');
            $service = app(OfficerTaskService::class);
            $task = $service->create($senior, $record->id, ['title' => 'Semak pelan sendiri', 'assignee_id' => $officer->id, 'due_date' => '2026-11-18']);
            $service->create($senior, $record->id, ['title' => 'Kerja pegawai lain', 'assignee_id' => $other->id, 'due_date' => '2026-11-19']);
            $this->actingAs($officer)->get(route('perancang.dashboard'))->assertOk()->assertSee($task->title)->assertDontSee('Kerja pegawai lain')
                ->assertViewHas('taskMetrics', ['accessible' => 1, 'mine' => 1, 'overdue' => 1]);
            $response = $this->actingAs($senior)->get(route('perancang.dashboard'))->assertOk()->assertSee('Kerja pegawai lain')
                ->assertViewHas('taskMetrics', ['accessible' => 2, 'mine' => 0, 'overdue' => 1]);
            $this->capture('perancang-tasks', $response->getContent());
            DB::table('core.role_permissions')->where('role_id', 'planning_officer')->where('permission_name', 'core.tasks.view')->delete();
            $this->actingAs($officer)->get(route('perancang.dashboard'))->assertOk()->assertDontSee($task->title)->assertViewHas('hasTasks', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_empty_dashboards_and_denied_accounts_do_not_leak_other_departments(): void
    {
        $planner = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->actingAs($planner)->get(route('perancang.dashboard'))->assertOk()->assertSee('Belum ada permohonan');
        foreach (['bangunan', 'kejuruteraan', 'landskap'] as $department) {
            $this->get(route($department.'.dashboard'))->assertForbidden();
        }
        DB::table('core.role_permissions')->where('role_id', 'penolong_pengarah')->where('permission_name', 'not like', 'library.%')->delete();
        foreach ([['is_active' => false], ['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null], ['role' => 'penolong_pengarah']] as $attributes) {
            $actor = User::factory()->create($attributes + ['department_id' => 'JPBD']);
            foreach (array_column(self::departments(), 0) as $department) {
                $response = $this->actingAs($actor)->get(route($department.'.dashboard'));
                $this->assertSame(403, $response->status(), json_encode($attributes).' '.$department);
            }
        }
    }

    private function capture(string $department, string $html): void
    {
        if (getenv('INDECS_CAPTURE_DASHBOARDS') !== '1') {
            return;
        }
        $directory = base_path('output/dashboard-preview');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory.'/'.$department.'.html', preg_replace('#http://localhost(?::\d+)?#', 'http://127.0.0.1:8030', $html));
    }
}
