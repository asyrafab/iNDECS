<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Contracts\TextExtractor;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\User;
use App\Services\Precheck\DraftDropService;
use App\Services\Precheck\ExtractedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Upload-first part 2, Task 2: the pre-check job fills empty draft details from dropped documents. */
final class DraftPrefillTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    public const LETTER = 'PERMOHONAN KELULUSAN BAGI CADANGAN MEMBINA MENARA TELEKOMUNIKASI DI ATAS LOT 101, MUKIM 6, SEBERANG PERAI TENGAH. UNTUK TETUAN CONTOH SDN. BHD.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        Storage::fake('local');
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
    }

    private ?User $owner = null;

    private function dropDraft(): ApplicantDraft
    {
        return app(DraftDropService::class)->createDraft($this->owner ??= $this->applicant(), 'TEL');
    }

    private function dropped(ApplicantDraft $draft, string $name): ApplicantDraftDocument
    {
        $path = "drafts/{$draft->id}/inbox/".uniqid().'.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF\n");

        return $this->draftDocument($draft, 'x', ['item_code' => null, 'document_type_id' => null, 'sequence' => 0,
            'original_name' => $name, 'path' => $path, 'sha256' => hash('sha256', uniqid('', true)),
            'precheck_status' => 'pending', 'readable' => null, 'text_chars' => null, 'matched_keywords' => null, 'scan_pending' => true]);
    }

    private function check(ApplicantDraftDocument $document, string $text, bool $read = true): void
    {
        $fake = new class(new ExtractedText($text)) implements TextExtractor
        {
            public function __construct(private ExtractedText $result) {}

            public function extract(string $absolutePath, string $mime): ExtractedText
            {
                return $this->result;
            }
        };
        $this->app->instance(TextExtractor::class, $fake);
        (new PrecheckDraftDocument($document->id, $read))->handle($fake);
    }

    private function revisions(ApplicantDraft $draft): Collection
    {
        return DB::table('core.applicant_draft_revisions')->where('draft_id', $draft->id)->orderBy('version')->get();
    }

    public function test_fills_provisional_title_and_empty_address_from_dropped_letter(): void
    {
        $draft = $this->dropDraft();
        $letter = $this->dropped($draft, 'SURAT PERMOHONAN.pdf');

        $this->check($letter, self::LETTER);

        $draft->refresh();
        $this->assertSame('CADANGAN MEMBINA MENARA TELEKOMUNIKASI', $draft->title);
        $this->assertSame('LOT 101, MUKIM 6, SEBERANG PERAI TENGAH', $draft->site_address);
        $this->assertTrue($draft->title_provisional, 'A filled title is unconfirmed until the applicant saves.');
        $this->assertEquals(['document_id' => $letter->id, 'name' => 'SURAT PERMOHONAN.pdf', 'preferred' => true], $draft->prefill_sources['title']);
        $this->assertSame('SURAT PERMOHONAN.pdf', $draft->prefill_sources['site_address']['name']);
        $this->assertSame(2, $draft->version);
        $revision = $this->revisions($draft)->last();
        $this->assertSame(2, (int) $revision->version);
        $this->assertSame($draft->user_id, (int) $revision->actor_id);
        $this->assertTrue(json_decode($revision->snapshot, true)['prefill']);
    }

    public function test_never_overwrites_applicant_values(): void
    {
        $draft = $this->dropDraft();
        $draft->forceFill(['title' => 'Tajuk saya', 'title_provisional' => false, 'site_address' => 'Alamat saya'])->save();

        $this->check($this->dropped($draft, 'SURAT PERMOHONAN.pdf'), self::LETTER);

        $draft->refresh();
        $this->assertSame('Tajuk saya', $draft->title);
        $this->assertSame('Alamat saya', $draft->site_address);
        $this->assertNull($draft->prefill_sources);
        $this->assertSame(1, $draft->version);
        $this->assertCount(1, $this->revisions($draft));
    }

    public function test_preferred_source_replaces_non_preferred_but_not_reverse(): void
    {
        $other = 'PERMOHONAN BAGI CADANGAN LAIN DI ATAS LOT 9. UNTUK TETUAN X';

        $draft = $this->dropDraft();
        $this->check($this->dropped($draft, 'LAMPIRAN.pdf'), $other);
        $this->check($this->dropped($draft, 'SURAT PERMOHONAN.pdf'), self::LETTER);
        $this->assertSame('CADANGAN MEMBINA MENARA TELEKOMUNIKASI', $draft->fresh()->title);
        $this->assertSame('LOT 101, MUKIM 6, SEBERANG PERAI TENGAH', $draft->fresh()->site_address);

        $reverse = $this->dropDraft();
        $this->check($this->dropped($reverse, 'SURAT PERMOHONAN.pdf'), self::LETTER);
        $this->check($this->dropped($reverse, 'LAMPIRAN.pdf'), $other);
        $this->assertSame('CADANGAN MEMBINA MENARA TELEKOMUNIKASI', $reverse->fresh()->title);
        $this->assertSame(2, $reverse->fresh()->version);
    }

    public function test_lighting_type_is_never_filled_outside_ljup(): void
    {
        $draft = $this->dropDraft();

        $this->check($this->dropped($draft, 'SURAT PERMOHONAN.pdf'), self::LETTER.' TIANG KONKRIT 10M');

        $this->assertNull($draft->fresh()->lighting_types);
        $this->assertArrayNotHasKey('lighting_types', $draft->fresh()->prefill_sources);
    }

    public function test_unread_file_fills_nothing(): void
    {
        $draft = $this->dropDraft();

        $this->check($this->dropped($draft, 'SURAT PERMOHONAN.pdf'), self::LETTER, read: false);

        $this->assertNull($draft->fresh()->prefill_sources);
        $this->assertSame(1, $draft->fresh()->version);
    }

    public function test_removed_draft_is_not_filled(): void
    {
        $draft = $this->dropDraft();
        $letter = $this->dropped($draft, 'SURAT PERMOHONAN.pdf');
        $draft->delete();

        $this->check($letter, self::LETTER);

        $this->assertNull(ApplicantDraft::withTrashed()->find($draft->id)->prefill_sources);
    }
}
