<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestDocumentCatalogues;
use Tests\TestCase;

final class WorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_planning_workspace_does_not_advertise_unauthorised_department_links(): void
    {
        $officer = User::query()->where('email', 'pegawai@indecs.test')->firstOrFail();
        $response = $this->actingAs($officer)->get(route('dashboard'));
        $response->assertOk()->assertSee('iNDECS Perancang')->assertSee('Panduan AMIRA')
            ->assertDontSee('href="'.route('bangunan.index').'"', false)
            ->assertDontSee('href="'.route('bangunan.legacy.index').'"', false);
        $this->capture('perancang-dashboard', $response);
        $this->capture('perancang-list', $this->get(route('perancang.index'))->assertOk());
        $this->capture('perancang-create', $this->get(route('perancang.create'))->assertOk());
    }

    public function test_registration_links_are_hidden_when_the_permission_is_removed(): void
    {
        DB::table('core.role_permissions')->where('role_id', 'planning_officer')
            ->where('permission_name', 'perancang.pkm.register')->delete();
        $officer = User::query()->where('email', 'pegawai@indecs.test')->firstOrFail();
        foreach ([route('dashboard'), route('perancang.index')] as $url) {
            $this->actingAs($officer)->get($url)->assertOk()
                ->assertDontSee('href="'.route('perancang.create').'"', false);
        }
    }

    public function test_bangunan_record_and_documents_share_guidance_without_changing_history(): void
    {
        $officer = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer', 'name' => 'Nur Aina']);
        TestDocumentCatalogues::seed($officer);
        $this->actingAs($officer)->post(route('bangunan.store'), [
            'application_type_id' => 'TEL', 'application_title' => 'Cadangan struktur telekomunikasi',
            'submission_date' => '2026-10-01', 'priority' => 'normal',
            'applicant' => ['party_type' => 'organisation', 'name' => 'Pemohon Contoh'],
            'site' => ['development_address' => 'Jalan Contoh', 'district' => 'Seberang Perai Tengah', 'mukim' => 'Mukim 11'],
            'lots' => [['lot_number' => '101']],
            'bangunan' => ['building_category' => 'Struktur', 'building_use' => 'Telekomunikasi'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $application = Application::query()->firstOrFail();
        $historyCount = DB::table('stage_histories')->count();
        $auditCount = DB::table('audit_logs')->count();
        $pages = [
            'bangunan-list' => route('bangunan.index'),
            'bangunan-create' => route('bangunan.create'),
            'bangunan-show' => route('bangunan.show', $application),
            'bangunan-documents' => route('bangunan.documents.index', $application),
            'bangunan-legacy' => route('bangunan.legacy.index'),
        ];
        foreach ($pages as $name => $url) {
            $response = $this->get($url)->assertOk()->assertSee('iNDECS Bangunan')
                ->assertSee('Sambungan AIRS belum tersedia')
                ->assertDontSee('href="'.route('perancang.index').'"', false);
            if (in_array($name, ['bangunan-show', 'bangunan-documents'], true)) {
                $response->assertSee('data-reference="'.$application->application_reference.'"', false);
            } else {
                $response->assertSee('data-reference=""', false);
            }
            $this->capture($name, $response);
        }
        $this->assertDatabaseCount('stage_histories', $historyCount);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertSame(1, $application->fresh()->row_version);
        $senior = User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']);
        $this->capture('bangunan-catalogue', $this->actingAs($senior)->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertOk());
    }

    public function test_multi_department_navigation_keeps_both_authorised_workspaces_reachable(): void
    {
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
        $this->actingAs($admin)->get(route('bangunan.index'))->assertOk()
            ->assertSee('href="'.route('perancang.index').'"', false);
        $this->get(route('perancang.index'))->assertOk()
            ->assertSee('href="'.route('bangunan.index').'"', false)
            ->assertDontSee('class="nav-link active" href="'.route('dashboard').'"', false);
    }

    public function test_public_identity_uses_shared_branding_without_staff_record_context(): void
    {
        foreach (['staff-login' => route('login'), 'public-login' => route('portal.login'), 'public-register' => route('portal.register')] as $name => $url) {
            $response = $this->get($url)->assertOk()->assertSee('Sambungan AIRS belum tersedia')
                ->assertSee('img/mbsp.png')->assertDontSee('data-stage="Pendaftaran"', false);
            $this->capture($name, $response);
        }
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('INDECS_CAPTURE_THEME') !== '1') {
            return;
        }
        $directory = base_path('.tools/theme-ui');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        // Rendered test fixtures only. No local application database or live identity is used.
        $html = preg_replace('#http://localhost(?::\d+)?#', 'http://127.0.0.1:8008', $response->getContent());
        file_put_contents($directory.'/'.$name.'.html', $html);
    }
}
