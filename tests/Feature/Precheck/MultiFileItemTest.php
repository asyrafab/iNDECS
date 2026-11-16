<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Contracts\TextExtractor;
use App\Enums\PrecheckLabel;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\BangunanDocumentCatalogueService;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\ExtractedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_more_files_than_the_limit_keeps_the_newest_current_and_loses_none(): void
    {
        $draft = $this->telDraft($this->applicant());
        $files = collect(range(1, 4))->map(fn () => $this->draftDocument($draft, 'pelan_lantai'));

        $this->assertSame($files->slice(1)->pluck('id')->all(), $this->ids($this->item($draft, 'pelan_lantai')['documents']));
        $this->assertSame(4, $draft->documents()->count());
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

    public function test_every_ljup_item_holds_five(): void
    {
        $catalogue = json_decode((string) file_get_contents(database_path('catalogues/ljup.v1.json')), true);

        foreach ($catalogue['items'] as $item) {
            $this->assertSame(5, $item['max_files'] ?? null, $item['code']);
        }
    }
}
