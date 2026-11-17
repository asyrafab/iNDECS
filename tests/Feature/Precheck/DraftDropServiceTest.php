<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\User;
use App\Services\Precheck\DraftDropService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

/** Upload-first part 1, Task 2: drop intake for loose files and ZIP archives. */
final class DraftDropServiceTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        Queue::fake();
        Storage::fake('local');
        $this->scanner(true);
        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'drop-'.uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    private function pdfBytes(string $marker): string
    {
        return "%PDF-1.4\n% {$marker}\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
    }

    private function pdf(string $name, string $marker): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pdfBytes($marker));
    }

    /** @param array<string, string> $entries */
    private function zip(array $entries): UploadedFile
    {
        $path = $this->tmp.DIRECTORY_SEPARATOR.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($entries as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        $zip->close();

        return new UploadedFile($path, 'pakej.zip', 'application/zip', null, true);
    }

    private function scanner(bool $clean): void
    {
        $this->app->instance(DocumentScanner::class, new class($clean) implements DocumentScanner
        {
            public function __construct(private bool $clean) {}

            public function assertClean(string $path): void
            {
                if (! $this->clean) {
                    throw ValidationException::withMessages(['document' => 'Fail dikesan tidak selamat.']);
                }
            }
        });
    }

    private function owner(ApplicantDraft $draft): User
    {
        return User::query()->findOrFail($draft->user_id);
    }

    private function draft(): ApplicantDraft
    {
        return $this->telDraft($this->applicant());
    }

    public function test_loose_pdf_becomes_unsorted_and_queues_precheck(): void
    {
        $draft = $this->draft();
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->pdf('geran.pdf', 'a'));

        $this->assertCount(1, $result->accepted);
        $doc = $result->accepted[0];
        $this->assertNull($doc->item_code);
        $this->assertNull($doc->document_type_id);
        $this->assertSame(0, $doc->sequence);
        $this->assertSame('pending', $doc->precheck_status);
        $this->assertStringStartsWith("drafts/{$draft->id}/inbox/", $doc->path);
        Storage::disk('local')->assertExists($doc->path);
        Queue::assertPushed(PrecheckDraftDocument::class, fn (PrecheckDraftDocument $job) => $job->documentId === $doc->id && $job->read === true);
    }

    public function test_zip_entries_become_unsorted_documents(): void
    {
        $draft = $this->draft();
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->zip([
            'folder/pelan.pdf' => $this->pdfBytes('p'), 'laporan.pdf' => $this->pdfBytes('l'), 'nota.txt' => 'teks',
        ]));

        $this->assertCount(2, $result->accepted);
        $this->assertSame(['pelan.pdf', 'laporan.pdf'], array_map(fn ($d) => $d->original_name, $result->accepted));
        $this->assertSame([['name' => 'nota.txt', 'reason' => 'Jenis fail tidak dibenarkan.']], $result->rejected);
        $this->assertSame(2, ApplicantDraftDocument::query()->whereNull('item_code')->count());
    }

    public function test_unsafe_zip_is_refused_whole(): void
    {
        $draft = $this->draft();
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->zip(['../evil.pdf' => $this->pdfBytes('e'), 'ok.pdf' => $this->pdfBytes('o')]));

        $this->assertSame([], $result->accepted);
        $this->assertSame([['name' => 'pakej.zip', 'reason' => 'Fail ZIP tidak selamat atau melebihi had dan tidak diproses.']], $result->rejected);
        $this->assertSame(0, ApplicantDraftDocument::query()->count());
    }

    public function test_nested_zip_refuses_archive(): void
    {
        $draft = $this->draft();
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->zip(['dalam.zip' => 'PK', 'ok.pdf' => $this->pdfBytes('o')]));

        $this->assertSame([], $result->accepted);
        $this->assertSame('pakej.zip', $result->rejected[0]['name']);
    }

    public function test_duplicate_file_is_rejected(): void
    {
        $draft = $this->draft();
        app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->pdf('geran.pdf', 'same'));
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->zip(['salinan.pdf' => $this->pdfBytes('same')]));

        $this->assertSame([], $result->accepted);
        $this->assertSame([['name' => 'salinan.pdf', 'reason' => 'Fail yang sama telah dimuat naik.']], $result->rejected);
        $this->assertSame(1, ApplicantDraftDocument::query()->count());
    }

    public function test_drop_does_not_scan_in_the_request(): void
    {
        // Pilot: ClamAV needs ~7 s per run and ~55 s for an 80 MB package, far past the 30 s request limit.
        $this->scanner(false);
        $draft = $this->draft();
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->pdf('geran.pdf', 'v'));

        $this->assertCount(1, $result->accepted);
        $this->assertTrue($result->accepted[0]->fresh()->scan_pending);
    }

    public function test_renamed_non_pdf_is_rejected(): void
    {
        $draft = $this->draft();
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, UploadedFile::fake()->createWithContent('palsu.pdf', '<html>bukan pdf</html>'));

        $this->assertSame('palsu.pdf', $result->rejected[0]['name'] ?? null);
        $this->assertSame(0, ApplicantDraftDocument::query()->count());
    }

    public function test_draft_document_limit_counts_unsorted(): void
    {
        config(['kaunter.precheck.max_documents_per_draft' => 1]);
        $draft = $this->draft();
        app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->pdf('a.pdf', 'a'));
        $result = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->pdf('b.pdf', 'b'));

        $this->assertSame([['name' => 'b.pdf', 'reason' => 'Had muat naik bagi draf ini telah dicapai.']], $result->rejected);
    }

    public function test_files_beyond_read_budget_are_queued_without_reading(): void
    {
        config(['kaunter.precheck.read_files_per_draft' => 1]);
        $draft = $this->draft();
        $first = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->pdf('a.pdf', 'a'))->accepted[0];
        $second = app(DraftDropService::class)->accept($this->owner($draft), $draft, $this->pdf('b.pdf', 'b'))->accepted[0];

        Queue::assertPushed(PrecheckDraftDocument::class, fn ($job) => $job->documentId === $first->id && $job->read === true);
        Queue::assertPushed(PrecheckDraftDocument::class, fn ($job) => $job->documentId === $second->id && $job->read === false);
    }

    public function test_create_draft_is_provisional(): void
    {
        $owner = $this->applicant();
        $draft = app(DraftDropService::class)->createDraft($owner, 'TEL');

        $this->assertTrue($draft->title_provisional);
        $this->assertSame('Draf baharu — '.now()->format('d/m/Y'), $draft->title);
        $this->assertSame(1, $draft->version);
        $this->assertSame($owner->id, $draft->user_id);
        $this->assertDatabaseHas('core.applicant_draft_revisions', ['draft_id' => $draft->id, 'version' => 1]);
    }
}
