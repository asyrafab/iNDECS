<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\FindingFeedbackService;
use App\Models\User;
use App\Services\Kejuruteraan\OfficerCheckPresenter;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FocusReviewTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    private function order(): array
    {
        return array_column(app(OfficerCheckPresenter::class)->itemsFor(ReviewVersion::query()->sole()), 'finding_id');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        return new DOMXPath($dom);
    }

    public function test_focus_is_default_with_collapsed_context_and_overview_remains_available(): void
    {
        [$application] = $this->paidLjup();
        $this->actingAs($this->engineer());
        $html = $this->get(route('kejuruteraan.show', $application))->assertOk()->assertSee('Simpan ulasan & seterusnya', false)->getContent();
        $dom = $this->xpath($html);
        $this->assertSame(1, $dom->query('//*[@data-review-mode="focus"]')->length);
        $this->assertSame(0, $dom->query('//details[@class="review-context-disclosure" and @open]')->length);
        $this->assertSame(0, $dom->query('//details[@class="technical-ai" and @open]')->length);
        $this->assertSame(1, $dom->query('//select[@data-review-queue-select]')->length);
        $overview = $this->xpath($this->get(route('kejuruteraan.show', ['application' => $application->id, 'review' => 'overview']))->assertOk()->getContent());
        $this->assertSame(1, $overview->query('//*[@data-review-mode="overview"]')->length);
        $this->assertSame(1, $overview->query('//details[@class="review-context-disclosure" and @open]')->length);
        $this->assertGreaterThan(0, $overview->query('//details[@class="technical-ai" and @open]')->length);
    }

    public function test_save_next_skips_reviewed_findings_ignores_client_destinations_and_preserves_application(): void
    {
        [$application] = $this->paidLjup();
        $ids = $this->order();
        $actor = $this->engineer();
        app(FindingFeedbackService::class)->record($actor, ReviewFinding::findOrFail($ids[2]), 'BELUM_DITUNAIKAN', 'Sudah diulas.');
        $before = $application->getAttributes();
        $history = $application->stageHistory()->count();
        $this->actingAs($actor)->post(route('kejuruteraan.findings.feedback', [$application, $ids[1]]), [
            'officer_status' => 'BELUM_DITUNAIKAN', 'officer_comment' => 'Ulasan tugas semasa.', 'after_save' => 'next', 'review_mode' => 'focus',
            'next_finding_id' => 'foreign-id', 'return_url' => 'https://evil.example/',
        ])->assertRedirect(route('kejuruteraan.show', ['application' => $application->id, 'review' => 'focus', 'finding' => $ids[3]]).'#finding-'.$ids[3]);
        $this->assertDatabaseHas('airs.review_finding_feedbacks', ['review_finding_id' => $ids[1], 'actor_id' => $actor->id, 'comment' => 'Ulasan tugas semasa.']);
        $this->assertSame($before, $application->fresh()->getAttributes());
        $this->assertSame($history, $application->stageHistory()->count());
    }

    public function test_last_pending_finding_returns_to_completion_message_without_an_official_decision(): void
    {
        [$application] = $this->paidLjup();
        $ids = $this->order();
        $actor = $this->engineer();
        foreach (array_slice($ids, 0, -1) as $id) {
            app(FindingFeedbackService::class)->record($actor, ReviewFinding::findOrFail($id), 'BELUM_DITUNAIKAN', 'Semakan pegawai.');
        }
        $before = $application->getAttributes();
        $last = end($ids);
        $this->actingAs($actor)->post(route('kejuruteraan.findings.feedback', [$application, $last]), [
            'officer_status' => 'BELUM_DITUNAIKAN', 'officer_comment' => 'Ulasan terakhir.', 'after_save' => 'next',
        ])->assertRedirect(route('kejuruteraan.show', ['application' => $application->id, 'review' => 'focus']).'#review-complete-1');
        $this->get(route('kejuruteraan.show', $application))->assertOk()->assertSee('Ini bukan keputusan rasmi.');
        $this->assertSame($before, $application->fresh()->getAttributes());
    }

    public function test_save_next_wraps_to_an_earlier_pending_finding(): void
    {
        [$application] = $this->paidLjup();
        $ids = $this->order();
        $actor = $this->engineer();
        $last = end($ids);
        $this->actingAs($actor)->post(route('kejuruteraan.findings.feedback', [$application, $last]), [
            'officer_status' => 'BELUM_DITUNAIKAN', 'after_save' => 'next',
        ])->assertRedirect(route('kejuruteraan.show', ['application' => $application->id, 'review' => 'focus', 'finding' => $ids[0]]).'#finding-'.$ids[0]);
    }

    public function test_validation_failure_keeps_the_current_finding_and_never_advances_or_saves(): void
    {
        [$application] = $this->paidLjup();
        $ids = $this->order();
        $current = $ids[4];
        $url = route('kejuruteraan.show', ['application' => $application->id, 'review' => 'focus', 'finding' => $current]);
        $this->actingAs($this->engineer())->from($url)->post(route('kejuruteraan.findings.feedback', [$application, $current]), [
            'feedback_finding_id' => $current, 'officer_status' => '', 'officer_comment' => 'Teks belum disimpan.', 'after_save' => 'next', 'review_mode' => 'focus',
        ])->assertRedirect($url)->assertSessionHasErrors('officer_status');
        $response = $this->get($url)->assertOk()->assertSee('Teks belum disimpan.');
        $dom = $this->xpath($response->getContent());
        $this->assertSame(1, $dom->query('//details[@id="finding-'.$current.'" and @open and @data-review-failed="true"]')->length);
        $this->assertDatabaseCount('airs.review_finding_feedbacks', 0);
    }

    public function test_save_next_keeps_permission_and_application_scope_checks(): void
    {
        [$application] = $this->paidLjup();
        $ids = $this->order();
        [$other] = $this->paidLjup();
        $data = ['officer_status' => 'BELUM_DITUNAIKAN', 'after_save' => 'next'];
        $this->actingAs(User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']))
            ->post(route('kejuruteraan.findings.feedback', [$application, $ids[0]]), $data)->assertForbidden();
        $this->actingAs($this->engineer())->post(route('kejuruteraan.findings.feedback', [$other, $ids[0]]), $data)->assertNotFound();
        $this->assertDatabaseCount('airs.review_finding_feedbacks', 0);
    }
}
