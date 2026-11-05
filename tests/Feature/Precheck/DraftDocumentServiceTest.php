<?php

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraftDocument;
use App\Services\Precheck\DraftDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DraftDocumentServiceTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        Queue::fake();
        Storage::fake('local');
        $this->scanner(clean: true);
    }

    public function test_upload_scans_stores_and_dispatches_once(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $doc = app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf());
        $this->assertSame(1, $doc->sequence);
        $this->assertSame('pending', $doc->precheck_status);
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringStartsWith("drafts/{$draft->id}/geran/", $doc->path);
        Queue::assertPushed(PrecheckDraftDocument::class, 1);
    }

    public function test_same_file_again_is_not_reprocessed(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $first = app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf());
        $again = app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf());
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, ApplicantDraftDocument::query()->count());
        Queue::assertPushed(PrecheckDraftDocument::class, 1);
        app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf("%PDF-1.4\n% versi kedua\n%%EOF\n"));
        $this->assertSame(2, ApplicantDraftDocument::query()->count());
    }

    public function test_lying_extension_is_rejected_in_malay_before_storage(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        try {
            app(DraftDocumentService::class)->upload($owner, $draft, 'geran', UploadedFile::fake()->createWithContent('geran.pdf', '<html><script>alert(1)</script></html>'));
            $this->fail('Disguised file must be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('fail dokumen', $e->errors()['file'][0]);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, ApplicantDraftDocument::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_scanner_unavailable_keeps_nothing(): void
    {
        $this->scanner(clean: false);
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        try {
            app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf());
            $this->fail('Upload must fail closed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Pengimbas keselamatan tidak tersedia', implode(' ', $e->errors()['file']));
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, ApplicantDraftDocument::query()->count());
    }

    public function test_item_not_in_checklist_is_rejected(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner, ['storeys' => 1]);
        $this->expectException(ValidationException::class);
        app(DraftDocumentService::class)->upload($owner, $draft, 'pelan_struktur', $this->pdf());
    }

    public function test_document_quota_per_draft(): void
    {
        config(['kaunter.precheck.max_documents_per_draft' => 2]);
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf("%PDF-1.4\n%a\n%%EOF\n"));
        app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf("%PDF-1.4\n%b\n%%EOF\n"));
        $this->expectException(ValidationException::class);
        app(DraftDocumentService::class)->upload($owner, $draft, 'geran', $this->pdf("%PDF-1.4\n%c\n%%EOF\n"));
    }

    public function test_confirm_only_for_needs_confirmation(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $unreadable = $this->draftDocument($draft, 'pelan_lantai', ['readable' => false]);
        try {
            app(DraftDocumentService::class)->confirm($owner, $unreadable);
            $this->fail('Unreadable files cannot be confirmed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Sila muat naik semula', $e->errors()['file'][0]);
        }
        $needs = $this->draftDocument($draft, 'geran', ['matched_keywords' => []]);
        app(DraftDocumentService::class)->confirm($owner, $needs);
        $this->assertNotNull($needs->fresh()->confirmed_at);
    }

    private function scanner(bool $clean): void
    {
        $this->app->instance(DocumentScanner::class, new class($clean) implements DocumentScanner
        {
            public function __construct(private bool $clean) {}

            public function assertClean(string $path): void
            {
                if (! $this->clean) {
                    throw ValidationException::withMessages(['document' => 'Pengimbas keselamatan tidak tersedia. Dokumen tidak disimpan; sila cuba semula selepas perkhidmatan dipulihkan.']);
                }
            }
        });
    }

    private function pdf(string $content = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('geran.pdf', $content);
    }
}
