<?php

namespace Tests\Feature\Precheck;

use App\Contracts\TextExtractor;
use App\Enums\PrecheckLabel;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\ExtractedText;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class PrecheckJobTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    private ApplicantDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        $this->draft = $this->telDraft($this->applicant());
    }

    public function test_keyword_match_gives_attached(): void
    {
        $doc = $this->runCheck('geran', new ExtractedText("SURAT HAKMILIK\nNo.   Lot 4412 Mukim 6, Seberang Perai Tengah"));
        $this->assertSame(['HAKMILIK', 'NO. LOT'], $doc->matched_keywords);
        $this->assertSame(PrecheckLabel::Attached, $this->label('geran'));
        $this->assertFalse($doc->ocr_used);
    }

    public function test_removed_draft_is_not_processed_by_queued_precheck(): void
    {
        $doc = $this->draftDocument($this->draft, 'geran', ['precheck_status' => 'pending']);
        $this->draft->delete();
        $extractor = $this->mock(TextExtractor::class);
        $extractor->shouldNotReceive('extract');

        (new PrecheckDraftDocument($doc->id))->handle($extractor);

        $this->assertSame('pending', $doc->fresh()->precheck_status);
    }

    public function test_no_keyword_match_gives_needs_confirmation(): void
    {
        $this->runCheck('geran', new ExtractedText(str_repeat('Pelan susun atur bangunan cadangan ', 3)));
        $this->assertSame(PrecheckLabel::NeedsConfirmation, $this->label('geran'));
    }

    public function test_corrupt_gives_unreadable(): void
    {
        $doc = $this->runCheck('pelan_lantai', new ExtractedText('', corrupt: true));
        $this->assertFalse($doc->readable);
        $this->assertSame(PrecheckLabel::Unreadable, $this->label('pelan_lantai'));
    }

    public function test_too_little_text_needs_confirmation_not_unreadable(): void
    {
        // A site photo or stamp OCRs to little text; it must stay confirmable, never a dead end.
        $doc = $this->runCheck('pelan_lantai', new ExtractedText('ab', ocrUsed: true));
        $this->assertTrue($doc->readable);
        $this->assertSame(PrecheckLabel::NeedsConfirmation, $this->label('pelan_lantai'));
    }

    public function test_failed_job_leaves_a_confirmable_label_not_pending(): void
    {
        $doc = $this->draftDocument($this->draft, 'geran', ['precheck_status' => 'pending', 'readable' => null, 'matched_keywords' => null]);
        (new PrecheckDraftDocument($doc->id))->failed(new \RuntimeException('worker killed'));
        $this->assertSame('done', $doc->fresh()->precheck_status);
        $this->assertTrue($doc->fresh()->ocr_unavailable);
        $this->assertSame(PrecheckLabel::NeedsConfirmation, $this->label('geran'));
    }

    public function test_a_second_upload_in_the_same_draft_is_not_dropped(): void
    {
        // One active check per draft is enforced by waiting (WithoutOverlapping), never by discarding.
        $doc = $this->draftDocument($this->draft, 'geran', ['precheck_status' => 'pending', 'readable' => null]);
        $job = new PrecheckDraftDocument($doc->id);
        $this->assertNotInstanceOf(ShouldBeUnique::class, $job);
        $overlap = collect($job->middleware())->first(fn ($m) => $m instanceof WithoutOverlapping);
        $this->assertNotNull($overlap);
        $this->assertSame($this->draft->id, $overlap->key);
        $this->assertGreaterThan(1, $job->tries);
    }

    public function test_ocr_unavailable_gives_needs_confirmation_not_error(): void
    {
        $doc = $this->runCheck('geran', new ExtractedText('', ocrUsed: true, ocrUnavailable: true));
        $this->assertTrue($doc->ocr_unavailable);
        $this->assertSame('done', $doc->precheck_status);
        $this->assertSame(PrecheckLabel::NeedsConfirmation, $this->label('geran'));
    }

    public function test_superseded_document_is_skipped(): void
    {
        $old = $this->draftDocument($this->draft, 'geran', ['precheck_status' => 'pending', 'readable' => null, 'matched_keywords' => null]);
        $this->draftDocument($this->draft, 'geran', ['precheck_status' => 'pending', 'readable' => null]);
        $this->fakeExtractor(new ExtractedText('HAKMILIK '.str_repeat('x', 40)), expectCalls: 0);
        (new PrecheckDraftDocument($old->id))->handle(app(TextExtractor::class));
        $this->assertSame('done', $old->fresh()->precheck_status);
        $this->assertNull($old->fresh()->matched_keywords);
    }

    public function test_job_runs_on_precheck_queue(): void
    {
        $doc = $this->draftDocument($this->draft, 'geran', ['precheck_status' => 'pending', 'readable' => null]);
        $this->assertSame('precheck', (new PrecheckDraftDocument($doc->id))->queue);
    }

    private function runCheck(string $code, ExtractedText $result): ApplicantDraftDocument
    {
        $doc = $this->draftDocument($this->draft, $code, ['precheck_status' => 'pending', 'readable' => null, 'matched_keywords' => null]);
        $this->fakeExtractor($result);
        (new PrecheckDraftDocument($doc->id))->handle(app(TextExtractor::class));

        return $doc->fresh();
    }

    private function label(string $code): PrecheckLabel
    {
        return collect(app(DraftChecklist::class)->for($this->draft)['items'])->firstWhere('code', $code)['label'];
    }

    private function fakeExtractor(ExtractedText $result, int $expectCalls = 1): void
    {
        $fake = new class($result) implements TextExtractor
        {
            public int $calls = 0;

            public function __construct(private ExtractedText $result) {}

            public function extract(string $absolutePath, string $mime): ExtractedText
            {
                $this->calls++;

                return $this->result;
            }
        };
        $this->app->instance(TextExtractor::class, $fake);
        $this->beforeApplicationDestroyed(fn () => $this->assertSame($expectCalls, $fake->calls));
    }
}
