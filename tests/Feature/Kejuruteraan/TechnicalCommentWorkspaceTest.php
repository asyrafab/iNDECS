<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Models\ReviewFinding;
use App\Airs\Review\FindingFeedbackService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TechnicalCommentWorkspaceTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    public function test_compact_rows_separate_evidence_ai_and_officer_assessment_and_count_saved_reviews(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        $finding = ReviewFinding::query()->firstOrFail();
        app(FindingFeedbackService::class)->record($actor, $finding, 'BELUM_DITUNAIKAN', 'Sahkan dimensi pada halaman 2.');
        $response = $this->actingAs($actor)->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee('Bukti sumber')->assertSee('Penjelasan AI')->assertSee('Ulasan pegawai')->assertSee('Sahkan dimensi pada halaman 2.');
        $dom = $this->dom($response->getContent());
        $this->assertGreaterThan(1, $dom->query('//details[@data-review-finding]')->length);
        $this->assertSame(1, $dom->query('//details[@data-review-reviewed="true"]')->length);
        $this->assertSame('1', trim($dom->query('//dl[@aria-label="Kemajuan ulasan"]/div[2]/dd')->item(0)->textContent));
        $row = '//details[@id="finding-'.$finding->id.'"]';
        $this->assertSame('Sahkan dimensi pada halaman 2.', $dom->query($row.'//textarea[@name="officer_comment"]')->item(0)->textContent);
        $this->assertSame('BELUM_DITUNAIKAN', $dom->query($row.'//select[@name="officer_status"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $dom->query('//details[@data-review-reviewed="false"]//select[@name="officer_status"]/option[@selected and @value!=""]')->length);
        $this->assertSame(1, $dom->query('//*[@data-review-tools and @hidden]')->length, 'JS controls are hidden until initialization; native rows remain available.');
    }

    public function test_failed_form_preserves_escaped_input_for_that_finding_only(): void
    {
        [$application] = $this->paidLjup();
        $finding = ReviewFinding::query()->orderByDesc('id')->firstOrFail();
        $text = '<script>unsaved_private_comment()</script>';
        $response = $this->actingAs($this->engineer())->withSession(['_old_input' => [
            'feedback_finding_id' => $finding->id, 'officer_status' => 'BELUM_DITUNAIKAN', 'officer_comment' => $text,
        ]])->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertDontSee($text, false)->assertSee(e($text), false);
        $dom = $this->dom($response->getContent());
        $row = '//details[@id="finding-'.$finding->id.'"]';
        $this->assertSame(1, $dom->query($row.'[@open]')->length);
        $this->assertSame($text, $dom->query($row.'//textarea[@name="officer_comment"]')->item(0)->textContent);
        $this->assertSame(1, substr_count($response->getContent(), e($text)));
    }

    public function test_earlier_check_is_read_only_and_latest_check_remains_commentable(): void
    {
        $owner = $this->applicant('technical-review-two@example.test');
        $draft = $this->readyLjupDraft($owner);
        [$application, $journey] = $this->paidLjup($draft, false);
        $this->ljupUpload($draft, 'pelan_lampu');
        app(PaidCheckJourneyService::class)->submitAmendment($owner, $journey);
        $response = $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk();
        $dom = $this->dom($response->getContent());
        $this->assertSame(0, $dom->query('//*[@id="semakan-1"]//form[@data-review-form]')->length);
        $this->assertGreaterThan(0, $dom->query('//*[@id="semakan-2"]//form[@data-review-form]')->length);
    }
}
