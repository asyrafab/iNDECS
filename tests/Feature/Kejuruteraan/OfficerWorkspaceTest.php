<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewVersion;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use App\Services\ApplicationLauncher;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OfficerWorkspaceTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    public function test_engineering_officer_sees_ljup_applications(): void
    {
        [$application] = $this->paidLjup();
        $this->actingAs($this->engineer())->get(route('kejuruteraan.index'))->assertOk()
            ->assertSee($application->application_reference)->assertSee('Diserahkan kepada Jabatan Kejuruteraan');
    }

    public function test_show_displays_ai_detail_and_shadow_outcome(): void
    {
        [$application] = $this->paidLjup();
        $version = ReviewVersion::query()->sole();
        $finding = ReviewFinding::query()->where('review_version_id', $version->id)->whereIn('status', ['PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI'])->firstOrFail();
        // The report carries the officer-facing (Malay) wording of the AI finding.
        $reportFinding = collect($version->reports()->latest()->first()->report_json['sections'])->flatMap(fn ($s) => $s['findings'])
            ->firstWhere('id', $finding->id);

        $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee($finding->rule_id)->assertSee($reportFinding['finding'])
            ->assertSee('Keputusan bayangan — tidak dikuatkuasakan')->assertSee('Keyakinan')
            ->assertSee('Skor kesediaan dokumen');
    }

    public function test_show_lists_missing_required_documents(): void
    {
        $owner = $this->applicant('psp-missing@example.test');
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');
        [$application] = $this->paidLjup($draft);

        $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee('Dokumen wajib tidak dilampirkan')->assertSee('Laporan Fotometrik');
    }

    public function test_officer_downloads_evidence_document(): void
    {
        [$application] = $this->paidLjup();
        $document = ApplicationDocument::query()->where('application_id', $application->id)->firstOrFail();
        $response = $this->actingAs($this->engineer())->get(route('kejuruteraan.documents.show', [$application, $document]));
        $response->assertOk();
        $this->assertSame(Storage::disk('local')->get($document->storage_path), $response->streamedContent());
    }

    public function test_other_departments_and_public_users_are_refused(): void
    {
        [$application] = $this->paidLjup();
        $document = ApplicationDocument::query()->where('application_id', $application->id)->firstOrFail();
        $builder = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        foreach ([route('kejuruteraan.index'), route('kejuruteraan.show', $application), route('kejuruteraan.documents.show', [$application, $document])] as $url) {
            $this->actingAs($builder)->get($url)->assertForbidden();
        }
        $public = $this->applicant('public@example.test');
        $this->actingAs($public)->get(route('kejuruteraan.show', $application))->assertForbidden();
    }

    public function test_document_of_another_application_is_not_found(): void
    {
        [$application] = $this->paidLjup();
        [$other] = $this->paidLjup();
        $foreign = ApplicationDocument::query()->where('application_id', $other->id)->firstOrFail();
        $this->actingAs($this->engineer())->get(route('kejuruteraan.documents.show', [$application, $foreign]))->assertNotFound();
    }

    public function test_district_officer_sees_ljup_application_without_sites(): void
    {
        [$application] = $this->paidLjup();
        $this->actingAs($this->engineer('engineering_officer', 'SPT'))->get(route('kejuruteraan.show', $application))->assertOk();
        $this->actingAs($this->engineer('engineering_officer', 'SPT'))->get(route('kejuruteraan.index'))->assertSee($application->application_reference);
    }

    public function test_non_ljup_application_is_not_shown(): void
    {
        $pkm = Application::query()->create(['application_type_id' => 'PKM', 'application_reference' => 'PKM/2026/09999', 'title' => 'Bukan LJUP',
            'submission_date' => today(), 'submission_year' => 2026, 'responsible_department_id' => 'JPBD',
            'current_stage' => ApplicationStage::Registration, 'current_status' => ApplicationStatus::Submitted]);
        $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $pkm))->assertNotFound();
    }

    public function test_workspace_layout_names_jabatan_kejuruteraan(): void
    {
        [$application] = $this->paidLjup();
        $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee('iNDECS Engineering')->assertSee('Jabatan Kejuruteraan')->assertDontSee('Jabatan Perancang Bandar');
    }

    public function test_earlier_check_is_collapsed_under_the_final_check(): void
    {
        $owner = $this->applicant('psp-collapse@example.test');
        $draft = $this->readyLjupDraft($owner);
        [$application, $journey] = $this->paidLjup($draft, false);
        $this->ljupUpload($draft, 'pelan_lampu');
        app(PaidCheckJourneyService::class)->submitAmendment($owner, $journey);

        $html = $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details[^>]*id="semakan-1"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<details[^>]*id="semakan-2"/', $html);
    }

    public function test_launcher_shows_kejuruteraan_for_engineering_officer(): void
    {
        $apps = app(ApplicationLauncher::class)->forUser($this->engineer());
        $tile = collect($apps)->firstWhere('name', 'iNDECS Engineering');
        $this->assertNotNull($tile);
        $this->assertSame(route('kejuruteraan.dashboard'), $tile['url']);
    }
}
