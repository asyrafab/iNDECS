<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Contracts\TextExtractor;
use App\Enums\PrecheckLabel;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\ExtractedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Upload-first part 1, Task 3: the pre-check job sorts dropped files into checklist items. */
final class AutoSortTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    private ApplicantDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        $this->draft = $this->telDraft($this->applicant());
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->scanner(null);
    }

    private function scanner(?string $failure): void
    {
        $this->app->instance(\App\Contracts\DocumentScanner::class, new class($failure) implements \App\Contracts\DocumentScanner
        {
            public int $calls = 0;

            public function __construct(private ?string $failure) {}

            public function assertClean(string $path): void
            {
                $this->calls++;
                if ($this->failure !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['document' => $this->failure]);
                }
            }
        });
    }

    private function dropped(string $name): ApplicantDraftDocument
    {
        $path = "drafts/{$this->draft->id}/inbox/".uniqid().'.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        return $this->draftDocument($this->draft, 'x', ['item_code' => null, 'document_type_id' => null, 'sequence' => 0,
            'original_name' => $name, 'path' => $path, 'sha256' => hash('sha256', uniqid('', true)),
            'precheck_status' => 'pending', 'readable' => null, 'text_chars' => null, 'matched_keywords' => null, 'scan_pending' => true]);
    }

    private function check(ApplicantDraftDocument $document, string $text, bool $read = true): ApplicantDraftDocument
    {
        $fake = new class(new ExtractedText($text)) implements TextExtractor
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
        (new PrecheckDraftDocument($document->id, $read))->handle($fake);
        if (! $read) {
            $this->assertSame(0, $fake->calls, 'An unread file must never reach the text extractor.');
        }

        return $document->fresh();
    }

    private function item(string $code): array
    {
        return collect(app(DraftChecklist::class)->for($this->draft)['items'])->firstWhere('code', $code);
    }

    public function test_confident_file_is_placed_in_item(): void
    {
        $doc = $this->check($this->dropped('geran.pdf'), 'SURAT HAKMILIK DAERAH SEBERANG PERAI');

        $this->assertSame('geran', $doc->item_code);
        $this->assertSame('auto', $doc->sort_source);
        $this->assertSame(3, $doc->sort_score);
        $this->assertSame(1, $doc->sequence);
        $this->assertNotNull($doc->document_type_id);
        $this->assertSame('done', $doc->precheck_status);
        $this->assertSame($doc->id, $this->item('geran')['document']->id);
    }

    public function test_ambiguous_file_stays_unsorted(): void
    {
        $doc = $this->check($this->dropped('pelan.pdf'), str_repeat('Lukisan cadangan bangunan ', 3));

        $this->assertNull($doc->item_code);
        $this->assertSame('done', $doc->precheck_status);
        $this->assertSame([$doc->id], array_map(fn ($d) => $d->id, app(DraftChecklist::class)->for($this->draft)['unsorted']));
    }

    public function test_higher_score_displaces_lower_auto_placement_to_tray(): void
    {
        $weak = $this->check($this->dropped('geran.pdf'), 'SURAT HAKMILIK');
        $strong = $this->check($this->dropped('geran hakmilik.pdf'), 'HAKMILIK NO. LOT 4412');

        $this->assertSame('geran', $strong->item_code);
        $this->assertNull($weak->fresh()->item_code);
        $this->assertSame(0, $weak->fresh()->sequence);
        $this->assertNull($weak->fresh()->document_type_id);
    }

    public function test_lower_score_does_not_displace(): void
    {
        $strong = $this->check($this->dropped('geran hakmilik.pdf'), 'HAKMILIK NO. LOT 4412');
        $weak = $this->check($this->dropped('geran.pdf'), 'SURAT HAKMILIK');

        $this->assertSame('geran', $strong->fresh()->item_code);
        $this->assertNull($weak->item_code);
    }

    public function test_manual_placement_is_never_displaced(): void
    {
        $manual = $this->draftDocument($this->draft, 'geran', ['sort_source' => 'manual']);
        $doc = $this->check($this->dropped('geran hakmilik.pdf'), 'HAKMILIK NO. LOT 4412');

        $this->assertNull($doc->item_code);
        $this->assertSame($manual->id, $this->item('geran')['document']->id);
    }

    public function test_text_only_match_stays_unsorted(): void
    {
        // Pilot: every LJUP form mentions "Pelan Lampu Jalan"; text alone must not place a file with an uninformative name.
        $doc = $this->check($this->dropped('dokumen (2).pdf'), 'SURAT HAKMILIK NO. LOT 4412');

        $this->assertNull($doc->item_code);
    }

    public function test_unread_file_is_sorted_by_name_and_needs_confirmation(): void
    {
        $doc = $this->check($this->dropped('geran_hakmilik.pdf'), 'TIDAK DIBACA', read: false);

        $this->assertSame('geran', $doc->item_code);
        $this->assertTrue($doc->ocr_unavailable);
        $this->assertTrue($doc->readable);
        $this->assertNull($doc->text_chars);
        $this->assertSame(PrecheckLabel::NeedsConfirmation, $this->item('geran')['label']);
    }

    public function test_matched_keywords_are_for_the_placed_item(): void
    {
        $doc = $this->check($this->dropped('geran.pdf'), 'SURAT HAKMILIK');

        $this->assertSame(['HAKMILIK'], $doc->matched_keywords);
    }

    public function test_unsorted_file_has_no_matched_keywords(): void
    {
        $doc = $this->check($this->dropped('pelan.pdf'), 'SURAT HAKMILIK PELAN');

        $this->assertNull($doc->item_code);
        $this->assertSame([], $doc->matched_keywords);
    }

    public function test_confirmed_auto_placement_is_never_displaced(): void
    {
        $weak = $this->check($this->dropped('geran.pdf'), 'SURAT HAKMILIK');
        $weak->forceFill(['confirmed_at' => now()])->save();
        $strong = $this->check($this->dropped('geran hakmilik.pdf'), 'HAKMILIK NO. LOT 4412');

        $this->assertSame('geran', $weak->fresh()->item_code);
        $this->assertNull($strong->item_code);
    }

    public function test_dropped_file_is_scanned_by_the_job_before_sorting(): void
    {
        $doc = $this->check($this->dropped('geran.pdf'), 'SURAT HAKMILIK');

        $this->assertSame(1, app(\App\Contracts\DocumentScanner::class)->calls);
        $this->assertFalse($doc->scan_pending);
        $this->assertSame('geran', $doc->item_code);
    }

    public function test_infected_dropped_file_is_deleted_and_explained_in_the_tray(): void
    {
        $this->scanner('Dokumen ditolak oleh imbasan keselamatan.');
        $dropped = $this->dropped('geran.pdf');
        $doc = $this->check($dropped, 'SURAT HAKMILIK');

        $this->assertNull($doc->item_code);
        $this->assertFalse($doc->scan_pending);
        $this->assertSame('Dokumen ditolak oleh imbasan keselamatan.', $doc->rejected_reason);
        $this->assertSame('done', $doc->precheck_status);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($dropped->path);
        $this->assertSame([$doc->id], array_map(fn ($d) => $d->id, app(DraftChecklist::class)->for($this->draft)['unsorted']));
    }

    public function test_failed_job_rejects_an_unscanned_file(): void
    {
        $dropped = $this->dropped('geran.pdf');
        (new PrecheckDraftDocument($dropped->id))->failed();

        $doc = $dropped->fresh();
        $this->assertNotNull($doc->rejected_reason);
        $this->assertFalse($doc->scan_pending);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($dropped->path);
    }
}
