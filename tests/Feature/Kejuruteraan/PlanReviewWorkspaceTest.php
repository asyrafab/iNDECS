<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Models\ApplicationDocument;
use App\Models\ApplicationParty;
use App\Models\Party;
use App\Services\Kejuruteraan\OfficerCheckPresenter;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlanReviewWorkspaceTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    public function test_background_precedes_review_and_uses_recorded_parties_and_status(): void
    {
        [$application] = $this->paidLjup();
        $officer = $this->engineer();
        $application->update(['assigned_to' => $officer->id, 'external_reference' => 'JKEJ-TEST-2026']);
        $party = Party::query()->create(['party_type' => 'company', 'name' => 'Nama pihak semasa']);
        ApplicationParty::query()->create(['application_id' => $application->id, 'party_id' => $party->id, 'role' => 'developer',
            'submitted_snapshot' => ['name' => 'Pemaju <Ujian>'], 'is_primary' => true]);
        $response = $this->actingAs($officer)->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee('Latar belakang permohonan')->assertSee('JKEJ-TEST-2026')->assertSee('Lot 101, Mukim 6')
            ->assertSee('Pegawai ditugaskan')->assertSee($officer->name)->assertSee('Lampu besi')
            ->assertSee('Pemaju &lt;Ujian&gt;', false)->assertDontSee('Pemaju <Ujian>', false);
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'data-technical-review'), strpos($html, 'review-background-title'));
        $this->assertSame('Pemaju <Ujian>', app(OfficerCheckPresenter::class)->for($application)['background']['parties'][0]['name']);
    }

    public function test_viewer_uses_scoped_manifest_documents_and_same_origin_private_route(): void
    {
        [$application] = $this->paidLjup();
        [$other] = $this->paidLjup();
        $view = app(OfficerCheckPresenter::class)->for($application);
        $documents = $view['checks'][0]['documents'];
        $ownIds = ApplicationDocument::query()->where('application_id', $application->id)->pluck('id')->all();
        $this->assertNotEmpty($documents);
        foreach ($documents as $document) {
            $this->assertContains($document['id'], $ownIds);
            $this->assertSame(route('kejuruteraan.documents.show', [$application, $document['id']]), $document['url']);
        }
        $response = $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee('data-review-viewer', false)->assertSee('data-review-document-frame', false)
            ->assertSee('target="technical-document-1"', false)->assertDontSee('target="_blank"', false);
        foreach (ApplicationDocument::query()->where('application_id', $other->id)->pluck('id') as $foreignId) {
            $response->assertDontSee(route('kejuruteraan.documents.show', [$other, $foreignId]), false);
            $this->get(route('kejuruteraan.documents.show', [$application, $foreignId]))->assertNotFound();
        }
        $this->get($documents[0]['url'])->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_historical_viewer_keeps_original_revision_and_does_not_load_while_collapsed(): void
    {
        $owner = $this->applicant('plan-history@example.test');
        $draft = $this->readyLjupDraft($owner);
        [$application, $journey] = $this->paidLjup($draft, false);
        $original = app(OfficerCheckPresenter::class)->for($application)['checks'][0]['documents'];
        $this->ljupUpload($draft, 'pelan_lampu');
        app(PaidCheckJourneyService::class)->submitAmendment($owner, $journey);
        $checks = app(OfficerCheckPresenter::class)->for($application)['checks'];
        $this->assertSame($original, $checks[0]['documents']);
        $this->assertNotSame(array_column($checks[0]['documents'], 'id'), array_column($checks[1]['documents'], 'id'));
        $html = $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//iframe[@id="technical-document-1" and @src]')->length);
        $this->assertSame(1, $xpath->query('//iframe[@id="technical-document-2" and @src]')->length);
    }

    public function test_unsafe_or_unsupported_documents_are_not_offered_for_embedding(): void
    {
        [$application] = $this->paidLjup();
        $documents = ApplicationDocument::query()->where('application_id', $application->id)->get();
        $documents[0]->update(['scan_status' => 'infected']);
        $documents[1]->update(['mime_type' => 'text/html']);
        $this->assertSame([], app(OfficerCheckPresenter::class)->for($application)['checks'][0]['documents']);
        $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee('Tiada PDF atau imej yang boleh dipratonton');
    }
}
