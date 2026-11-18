<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\DocumentScanner;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentChecklist;
use App\Models\User;
use App\Services\LandskapRegistrationService;
use Database\Seeders\LandskapWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LandskapWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private User $senior;

    private User $ruleAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->officer = User::factory()->create(['department_id' => 'JLAND', 'role' => 'landscape_officer', 'name' => 'Pegawai Landskap']);
        $this->senior = User::factory()->create(['department_id' => 'JLAND', 'role' => 'senior_landscape_officer']);
        $this->ruleAdmin = User::factory()->create(['department_id' => 'JLAND', 'role' => 'system_admin']);
        $this->actingAs($this->officer);
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
    }

    public function test_registration_persists_pl_application_and_landscape_extension_fields(): void
    {
        $this->post(route('landskap.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $application = Application::query()->firstOrFail();

        $this->assertSame('PL/2026/00001', $application->application_reference);
        $this->assertSame('JLAND', $application->responsible_department_id);
        $this->assertSame('PL', $application->application_type_id);
        $this->assertSame(ApplicationStage::Registration, $application->current_stage);
        $this->assertSame(ApplicationStatus::Submitted, $application->current_status);
        $this->assertNotNull($application->workflow_template_id);

        $landscape = $application->landscape;
        $this->assertNotNull($landscape);
        $this->assertSame('Perumahan dan Kawasan Lapang', $landscape->landscape_category);
        $this->assertSame('1250.5000', $landscape->open_space_area);
        $this->assertSame('850.2500', $landscape->softscape_area);
        $this->assertSame('400.2500', $landscape->hardscape_area);
        $this->assertSame(12, $landscape->existing_trees);
        $this->assertSame(8, $landscape->retained_trees);
        $this->assertSame(45, $landscape->proposed_trees);
        $this->assertSame('Bucida molineti, Hopea odorata', $landscape->proposed_species);
        $this->assertSame('Jalur penampan hijau 3m sepanjang sempadan utara', $landscape->buffer_planting);
        $this->assertSame('Taman permainan kanak-kanak dan laluan pejalan kaki', $landscape->recreation_facilities);
        $this->assertSame('Nisbah gantian 1:3 bagi 4 batang pokok ditebang', $landscape->replacement_conditions);

        $this->assertDatabaseHas('stage_histories', [
            'application_id' => $application->id,
            'actor_id' => $this->officer->id,
            'action' => 'application_registered',
            'remarks' => 'Permohonan iNDECS Landscape didaftarkan.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $application->id,
            'actor_id' => $this->officer->id,
            'event' => 'created',
        ]);

        $this->get(route('landskap.show', $application))->assertOk()
            ->assertSee($application->application_reference)
            ->assertSee('Perumahan dan Kawasan Lapang')
            ->assertSee('Bucida molineti, Hopea odorata')
            ->assertSee('Mulakan pengesahan dokumen');
        $this->get(route('landskap.index'))->assertOk()->assertSee($application->application_reference);
    }

    public function test_launcher_and_workspace_navigation_expose_landscape_to_authorised_staff(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('iNDECS Landscape')
            ->assertSee('href="'.route('landskap.index').'"', false)
            ->assertDontSee('href="'.route('bangunan.index').'"', false);

        $this->get(route('dashboard'))->assertRedirect(route('landskap.index'));
        $this->get(route('landskap.index'))->assertOk()
            ->assertSee('iNDECS Landscape')
            ->assertSee('Jabatan Landskap')
            ->assertSee('href="'.route('landskap.create').'"', false);

        $pp = User::factory()->create(['department_id' => 'JLAND', 'role' => 'penolong_pengarah']);
        $this->assertTrue($pp->canAccessLandskap());
        $this->assertTrue($pp->canManageLibrary('JLAND'));
        $this->assertFalse($pp->canPerformLandskap('register'));
        $this->actingAs($pp)->get(route('landskap.index'))->assertOk()
            ->assertDontSee('href="'.route('landskap.create').'"', false);
    }

    public function test_district_and_department_scoping_enforced_across_landscape_routes(): void
    {
        $spt = app(LandskapRegistrationService::class)->register($this->payload(), $this->officer);

        $spuOfficer = User::factory()->create([
            'department_id' => 'JLAND',
            'role' => 'landscape_officer',
            'district' => 'Seberang Perai Utara',
        ]);
        $this->actingAs($spuOfficer)->get(route('landskap.index'))
            ->assertOk()
            ->assertDontSee($spt->application_reference)
            ->assertViewHas('metrics', fn ($metrics) => $metrics['total'] === 0);
        $this->get(route('landskap.show', $spt))->assertForbidden();
        $this->post(route('landskap.transition', $spt), ['action' => 'start_document_verification', 'row_version' => 1])->assertForbidden();
        $this->post(route('landskap.store'), $this->payload())->assertSessionHasErrors('site.district');

        $planner = User::query()->where('email', 'pegawai@indecs.test')->firstOrFail();
        $this->actingAs($planner)->get(route('landskap.index'))->assertForbidden();
        $this->post(route('landskap.store'), $this->payload())->assertForbidden();
    }

    public function test_document_catalogue_and_verification_workflow_complete_end_to_end(): void
    {
        $cataloguePayload = [
            'version' => 0,
            'source_reference' => 'Garis Panduan Pelan Landskap MBSP 2026',
            'items' => [
                ['code' => 'pelan_landskap', 'name' => 'Pelan Induk Landskap', 'is_required' => true],
                ['code' => 'inventori_pokok', 'name' => 'Laporan Inventori Pokok', 'is_required' => false],
            ],
        ];

        $this->post(route('landskap.document-catalogue.publish', 'PL'), $cataloguePayload)->assertForbidden();
        $this->actingAs($this->senior)->get(route('landskap.document-catalogue.edit', 'PL'))->assertForbidden();

        $this->actingAs($this->ruleAdmin)->get(route('landskap.document-catalogue.edit', 'PL'))->assertOk();
        $this->post(route('landskap.document-catalogue.publish', 'PL'), $cataloguePayload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('landskap.document-catalogue.edit', 'PL'));

        $this->assertDatabaseHas('core.document_catalogues', [
            'application_type_id' => 'PL',
            'version' => 1,
            'source_reference' => 'Garis Panduan Pelan Landskap MBSP 2026',
        ]);
        $this->assertDatabaseHas('document_types', [
            'code' => 'land_pl_pelan_landskap',
            'name' => 'Pelan Induk Landskap',
        ]);

        $this->actingAs($this->officer);
        $application = app(LandskapRegistrationService::class)->register($this->payload(), $this->officer);

        $this->post(route('landskap.transition', $application), [
            'action' => 'start_document_verification',
            'row_version' => $application->row_version,
            'remarks' => 'Semakan dokumen landskap dimulakan',
        ])->assertSessionHasNoErrors()->assertRedirect(route('landskap.show', $application));

        $application->refresh();
        $this->assertSame(ApplicationStage::DocumentVerification, $application->current_stage);
        $this->assertSame(ApplicationStatus::InReview, $application->current_status);

        $checklist = DocumentChecklist::query()->where('application_id', $application->id)->firstOrFail();
        $requiredItem = $checklist->items()->where('is_required', true)->firstOrFail();

        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $this->post(route('landskap.documents.store', [$application, $requiredItem]), [
            'row_version' => $application->row_version,
            'document' => UploadedFile::fake()->createWithContent('pelan-landskap.pdf', $pdf),
        ])->assertSessionHasNoErrors();

        $document = ApplicationDocument::query()->where('application_id', $application->id)->firstOrFail();
        $this->get(route('landskap.documents.download', [$application, $document]))
            ->assertOk()
            ->assertDownload('pelan-landskap.pdf');

        $this->post(route('landskap.documents.verify', [$application, $requiredItem]), [
            'row_version' => $application->fresh()->row_version,
            'document_id' => $document->id,
            'status' => 'accepted',
        ])->assertSessionHasNoErrors();

        $this->post(route('landskap.transition', $application), [
            'action' => 'complete_document_verification',
            'row_version' => $application->fresh()->row_version,
            'remarks' => 'Semua dokumen landskap disahkan lengkap',
        ])->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStage::FeeAssessment, $application->current_stage);
        $this->assertSame(ApplicationStatus::AwaitingPayment, $application->current_status);
        $this->assertDatabaseHas('core.document_checklists', [
            'application_id' => $application->id,
            'status' => 'completed',
        ]);
    }

    public function test_landskap_seeder_is_idempotent(): void
    {
        $templates = DB::table('core.workflow_templates')->count();
        $transitions = DB::table('core.workflow_transitions')->count();
        $grants = DB::table('core.role_permissions')->count();

        $this->seed(LandskapWorkflowSeeder::class);
        $this->seed(LandskapWorkflowSeeder::class);

        $this->assertDatabaseCount('core.workflow_templates', $templates);
        $this->assertDatabaseCount('core.workflow_transitions', $transitions);
        $this->assertDatabaseCount('core.role_permissions', $grants);
    }

    private function payload(): array
    {
        return [
            'application_type_id' => 'PL',
            'application_title' => 'Cadangan Pelan Landskap Taman Seri Impian',
            'application_description' => 'Cadangan landskap lembut dan kejur bagi pembangunan perumahan.',
            'external_reference' => 'OSC/PL/2026/010',
            'submission_date' => '2026-10-15',
            'priority' => 'normal',
            'applicant' => [
                'party_type' => 'organisation',
                'name' => 'Pemaju Seri Impian Sdn. Bhd.',
                'registration_number' => '202601009988',
                'email' => 'pemaju@impian.test',
                'phone' => '04-5001122',
                'address' => 'Bukit Mertajam, Pulau Pinang',
            ],
            'consultant' => [
                'party_type' => 'organisation',
                'name' => 'Arkitek Landskap Hijau PLT',
                'registration_number' => 'ILAM-402',
            ],
            'site' => [
                'development_address' => 'Lot 801 & 802, Mukim 15',
                'district' => 'Seberang Perai Tengah',
                'mukim' => 'Mukim 15',
            ],
            'lots' => [
                ['lot_number' => '801'],
                ['lot_number' => '802'],
            ],
            'landskap' => [
                'landscape_category' => 'Perumahan dan Kawasan Lapang',
                'open_space_area' => 1250.5,
                'softscape_area' => 850.25,
                'hardscape_area' => 400.25,
                'existing_trees' => 12,
                'retained_trees' => 8,
                'proposed_trees' => 45,
                'proposed_species' => 'Bucida molineti, Hopea odorata',
                'buffer_planting' => 'Jalur penampan hijau 3m sepanjang sempadan utara',
                'recreation_facilities' => 'Taman permainan kanak-kanak dan laluan pejalan kaki',
                'replacement_conditions' => 'Nisbah gantian 1:3 bagi 4 batang pokok ditebang',
            ],
        ];
    }
}
