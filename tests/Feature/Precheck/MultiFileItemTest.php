<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Contracts\TextExtractor;
use App\Enums\PrecheckLabel;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\BangunanDocumentCatalogueService;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\DraftDocumentPlacer;
use App\Services\Precheck\DraftDocumentService;
use App\Services\Precheck\ExtractedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Checklist items that hold several current files (catalogue max_files). */
final class MultiFileItemTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
        app(BangunanDocumentCatalogueService::class)->publish('TEL', ['version' => 0, 'source_reference' => 'Ujian', 'items' => [
            ['code' => 'geran', 'name' => 'Geran tanah', 'is_required' => true, 'precheck_keywords' => ['HAKMILIK']],
            ['code' => 'pelan_lantai', 'name' => 'Pelan lantai', 'is_required' => true, 'max_files' => 3],
            ['code' => 'pelan_struktur', 'name' => 'Pelan struktur', 'is_required' => true, 'conditions' => ['storeys_min' => 3], 'max_files' => 3],
            ['code' => 'gambar', 'name' => 'Gambar tapak', 'is_required' => false],
        ]], $admin);
    }

    private function item(ApplicantDraft $draft, string $code): array
    {
        return collect(app(DraftChecklist::class)->for($draft->fresh())['items'])->firstWhere('code', $code);
    }

    private function ids(array $documents): array
    {
        return array_map(fn ($document) => $document->id, $documents);
    }

    public function test_catalogue_stores_max_files(): void
    {
        $max = fn (string $code) => DocumentRequirement::query()->where('is_active', true)
            ->where('document_type_id', DocumentType::query()->where('code', 'bina_tel_'.$code)->value('id'))->value('max_files');

        $this->assertSame(3, $max('pelan_lantai'));
        $this->assertSame(1, $max('geran'));
    }

    public function test_multi_item_lists_every_placed_file_and_worst_label(): void
    {
        $draft = $this->telDraft($this->applicant());
        $first = $this->draftDocument($draft, 'pelan_lantai');
        $second = $this->draftDocument($draft, 'pelan_lantai', ['text_chars' => 5]);

        $item = $this->item($draft, 'pelan_lantai');

        $this->assertSame([$first->id, $second->id], $this->ids($item['documents']));
        $this->assertSame(3, $item['max_files']);
        $this->assertSame($second->id, $item['document']->id);
        $this->assertSame(PrecheckLabel::NeedsConfirmation, $item['label']);
    }

    public function test_single_item_still_shows_newest_only(): void
    {
        $draft = $this->telDraft($this->applicant());
        $this->draftDocument($draft, 'geran');
        $newest = $this->draftDocument($draft, 'geran');

        $this->assertSame([$newest->id], $this->ids($this->item($draft, 'geran')['documents']));
    }

    private function republish(array $maxFiles): void
    {
        $admin = User::query()->where('department_id', 'JBANG')->where('role', 'system_admin')->firstOrFail();
        $version = (int) DB::table('core.document_catalogues')->where('application_type_id', 'TEL')->value('version');
        app(BangunanDocumentCatalogueService::class)->publish('TEL', ['version' => $version, 'source_reference' => 'Ujian', 'items' => [
            ['code' => 'geran', 'name' => 'Geran tanah', 'is_required' => true, 'precheck_keywords' => ['HAKMILIK'], 'max_files' => $maxFiles['geran'] ?? 1],
            ['code' => 'pelan_lantai', 'name' => 'Pelan lantai', 'is_required' => true, 'max_files' => $maxFiles['pelan_lantai'] ?? 3],
            ['code' => 'pelan_struktur', 'name' => 'Pelan struktur', 'is_required' => true, 'conditions' => ['storeys_min' => 3], 'max_files' => 3],
            ['code' => 'gambar', 'name' => 'Gambar tapak', 'is_required' => false],
        ]], $admin);
    }

    public function test_files_over_a_lowered_limit_stay_visible_and_removable(): void
    {
        Queue::fake();
        $draft = $this->telDraft($this->applicant());
        $files = [$this->upload($draft, 'pelan_lantai', 'a'), $this->upload($draft, 'pelan_lantai', 'b'), $this->upload($draft, 'pelan_lantai', 'c')];
        $this->republish(['pelan_lantai' => 1]);

        $this->assertSame($this->ids($files), $this->ids($this->item($draft, 'pelan_lantai')['documents']));
        app(DraftDocumentService::class)->remove(User::query()->findOrFail($draft->user_id), $files[0]);
        $this->assertSame([$files[1]->id, $files[2]->id], $this->ids($this->item($draft, 'pelan_lantai')['documents']));
    }

    public function test_raising_the_limit_does_not_revive_replaced_uploads(): void
    {
        Queue::fake();
        $draft = $this->telDraft($this->applicant());
        $this->upload($draft, 'geran', 'lama');
        $newest = $this->upload($draft, 'geran', 'baru');
        $this->republish(['geran' => 5]);

        $this->assertSame([$newest->id], $this->ids($this->item($draft, 'geran')['documents']));
        app(DraftDocumentService::class)->remove(User::query()->findOrFail($draft->user_id), $newest);
        $this->assertSame([], $this->item($draft, 'geran')['documents'], 'Removing a file never brings back a replaced one.');
    }

    public function test_removing_one_file_of_a_stale_multi_item_keeps_the_others(): void
    {
        $draft = $this->telDraft($this->applicant(), ['storeys' => 4]);
        $files = collect(['a', 'b', 'c'])->map(function ($marker) use ($draft) {
            $file = $this->draftDocument($draft, 'pelan_struktur', ['original_name' => $marker.'.pdf']);
            Storage::disk('local')->put($file->path, 'x');

            return $file;
        });
        $draft->forceFill(['storeys' => 1])->save();
        $service = app(DraftDocumentService::class);
        $owner = User::query()->findOrFail($draft->user_id);

        $service->remove($owner, $files[0]);

        $this->assertEqualsCanonicalizing([$files[1]->id, $files[2]->id], $this->ids(app(DraftChecklist::class)->for($draft->fresh())['unsorted']));
        $this->assertTrue(Storage::disk('local')->exists($files[1]->path));
    }

    public function test_place_refuses_a_full_item_and_duplicate_bytes(): void
    {
        $draft = $this->telDraft($this->applicant());
        $placer = app(DraftDocumentPlacer::class);
        foreach (['a', 'b', 'c'] as $marker) {
            $placer->place($this->trayFile($draft, $marker), 'pelan_lantai', 'manual');
        }

        $this->refused(fn () => $placer->place($this->trayFile($draft, 'd'), 'pelan_lantai', 'manual'),
            'Dokumen ini sudah mempunyai 3 fail. Buang satu fail sebelum menambah.');
    }

    public function test_duplicate_bytes_from_the_tray_are_not_placed_twice(): void
    {
        $draft = $this->telDraft($this->applicant());
        $placer = app(DraftDocumentPlacer::class);
        $placer->place($this->trayFile($draft, 'a'), 'pelan_lantai', 'manual');
        $copy = $this->trayFile($draft, 'a-salinan');
        $copy->forceFill(['sha256' => hash('sha256', 'a')])->save();

        $this->assertFalse($placer->autoPlace($copy, [['code' => 'pelan_lantai', 'score' => 6, 'name_score' => 1]]));
        $this->refused(fn () => $placer->place($copy, 'pelan_lantai', 'manual'), 'Fail yang sama telah dimuat naik.');
    }

    public function test_stale_multi_item_sends_every_file_to_tray(): void
    {
        $draft = $this->telDraft($this->applicant(), ['storeys' => 4]);
        $a = $this->draftDocument($draft, 'pelan_struktur');
        $b = $this->draftDocument($draft, 'pelan_struktur');
        $draft->forceFill(['storeys' => 1])->save();

        $tray = $this->ids(app(DraftChecklist::class)->for($draft->fresh())['unsorted']);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $tray);
    }

    public function test_job_reads_every_file_of_a_multi_item(): void
    {
        $draft = $this->telDraft($this->applicant());
        $older = $this->draftDocument($draft, 'pelan_lantai', ['precheck_status' => 'pending', 'readable' => null]);
        $this->draftDocument($draft, 'pelan_lantai', ['precheck_status' => 'pending', 'readable' => null]);
        Storage::disk('local')->put($older->path, "%PDF-1.4\n%%EOF\n");
        $extractor = new class implements TextExtractor
        {
            public function extract(string $absolutePath, string $mime): ExtractedText
            {
                return new ExtractedText(str_repeat('PELAN LANTAI ', 10));
            }
        };

        (new PrecheckDraftDocument($older->id))->handle($extractor);

        $this->assertTrue($older->fresh()->readable);
        $this->assertSame(PrecheckLabel::Attached, DraftChecklist::label($older->fresh(), []));
    }

    private function pdf(string $marker): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($marker.'.pdf', "%PDF-1.4\n% {$marker}\n%%EOF\n");
    }

    private function upload(ApplicantDraft $draft, string $code, string $marker): ApplicantDraftDocument
    {
        return app(DraftDocumentService::class)->upload(User::query()->findOrFail($draft->user_id), $draft, $code, $this->pdf($marker));
    }

    private function trayFile(ApplicantDraft $draft, string $marker): ApplicantDraftDocument
    {
        $path = "drafts/{$draft->id}/inbox/{$marker}.pdf";
        Storage::disk('local')->put($path, "%PDF-1.4\n% {$marker}\n%%EOF\n");

        return $this->draftDocument($draft, 'x', ['item_code' => null, 'document_type_id' => null, 'sequence' => 0,
            'original_name' => $marker.'.pdf', 'path' => $path, 'sha256' => hash('sha256', $marker)]);
    }

    private function refused(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a refusal.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()['file'] ?? null);
        }
    }

    public function test_upload_adds_to_multi_item_until_full(): void
    {
        Queue::fake();
        $draft = $this->telDraft($this->applicant());
        $files = [$this->upload($draft, 'pelan_lantai', 'a'), $this->upload($draft, 'pelan_lantai', 'b'), $this->upload($draft, 'pelan_lantai', 'c')];

        $this->assertSame($this->ids($files), $this->ids($this->item($draft, 'pelan_lantai')['documents']));
        $this->refused(fn () => $this->upload($draft, 'pelan_lantai', 'd'), 'Dokumen ini sudah mempunyai 3 fail. Buang satu fail sebelum menambah.');
        // A single-file item still replaces.
        $this->upload($draft, 'geran', 'g1');
        $this->upload($draft, 'geran', 'g2');
        $this->assertCount(1, $this->item($draft, 'geran')['documents']);
    }

    public function test_duplicate_bytes_refused_in_multi_item(): void
    {
        Queue::fake();
        $draft = $this->telDraft($this->applicant());
        $this->upload($draft, 'pelan_lantai', 'a');

        $this->refused(fn () => $this->upload($draft, 'pelan_lantai', 'a'), 'Fail yang sama telah dimuat naik.');
    }

    public function test_autoplace_fills_multi_item_without_displacing(): void
    {
        $draft = $this->telDraft($this->applicant());
        $placer = app(DraftDocumentPlacer::class);
        $ranked = [['code' => 'pelan_lantai', 'score' => 6, 'name_score' => 1]];
        $placed = collect(['a', 'b', 'c'])->map(fn ($marker) => $this->trayFile($draft, $marker));
        foreach ($placed as $file) {
            $this->assertTrue($placer->autoPlace($file, $ranked));
        }
        $fourth = $this->trayFile($draft, 'd');

        $this->assertFalse($placer->autoPlace($fourth, [['code' => 'pelan_lantai', 'score' => 9, 'name_score' => 1]]));
        $this->assertSame($placed->pluck('id')->all(), $this->ids($this->item($draft, 'pelan_lantai')['documents']));
        $this->assertNull($fourth->fresh()->item_code);
    }

    public function test_assign_into_full_item_refused(): void
    {
        Queue::fake();
        $draft = $this->telDraft($this->applicant());
        foreach (['a', 'b', 'c'] as $marker) {
            $this->upload($draft, 'pelan_lantai', $marker);
        }
        $loose = $this->trayFile($draft, 'd');

        $this->refused(fn () => app(DraftDocumentService::class)->assign(User::query()->findOrFail($draft->user_id), $loose, 'pelan_lantai'),
            'Dokumen ini sudah mempunyai 3 fail. Buang satu fail sebelum menambah.');
    }

    public function test_placed_file_in_multi_item_can_be_removed(): void
    {
        Queue::fake();
        $draft = $this->telDraft($this->applicant());
        $keep = $this->upload($draft, 'pelan_lantai', 'a');
        $drop = $this->upload($draft, 'pelan_lantai', 'b');
        $single = $this->upload($draft, 'geran', 'g');
        $service = app(DraftDocumentService::class);
        $owner = User::query()->findOrFail($draft->user_id);

        $service->remove($owner, $drop);

        $this->assertSame([$keep->id], $this->ids($this->item($draft, 'pelan_lantai')['documents']));
        $this->assertFalse(Storage::disk('local')->exists($drop->path));
        // A single-file item's file is still replaced by re-uploading, not removed.
        $this->refused(fn () => $service->remove($owner, $single),
            'Hanya fail yang belum dipadankan boleh dibuang. Gantikan dokumen senarai semak dengan muat naik semula.');
    }

    public function test_confirm_each_file_in_multi_item(): void
    {
        $draft = $this->telDraft($this->applicant());
        $first = $this->draftDocument($draft, 'pelan_lantai', ['text_chars' => 5]);
        $second = $this->draftDocument($draft, 'pelan_lantai', ['text_chars' => 5]);
        $service = app(DraftDocumentService::class);
        $owner = User::query()->findOrFail($draft->user_id);

        $service->confirm($owner, $first);
        $this->assertSame(PrecheckLabel::NeedsConfirmation, $this->item($draft, 'pelan_lantai')['label']);
        $service->confirm($owner, $second);

        $this->assertSame(PrecheckLabel::Attached, $this->item($draft, 'pelan_lantai')['label']);
    }

    public function test_every_ljup_item_holds_five(): void
    {
        $catalogue = json_decode((string) file_get_contents(database_path('catalogues/ljup.v1.json')), true);

        foreach ($catalogue['items'] as $item) {
            $this->assertSame(5, $item['max_files'] ?? null, $item['code']);
        }
    }
}
