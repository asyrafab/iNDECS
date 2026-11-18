<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\ApplicationType;
use App\Models\DocumentType;
use App\Services\Precheck\DraftChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Upload-first part 1, Task 4: drop zone, folder pick and the "Fail belum dipadankan" tray. */
final class DropUploadPagesTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        Storage::fake('local');
        Queue::fake();
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
    }

    private function pdf(string $name, string $marker = 'a'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% {$marker}\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    private function tray(ApplicantDraft $draft, string $name = 'fail.pdf', array $state = []): ApplicantDraftDocument
    {
        $path = "drafts/{$draft->id}/inbox/".uniqid().'.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n% ".uniqid()."\n%%EOF\n");

        return $this->draftDocument($draft, 'x', array_replace(['item_code' => null, 'document_type_id' => null, 'sequence' => 0,
            'original_name' => $name, 'path' => $path, 'sha256' => hash('sha256', uniqid('', true))], $state));
    }

    public function test_create_page_leads_with_drop_zone(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.drafts.create'))->assertOk()
            ->assertSee('Seret fail atau folder ke sini')->assertSee('Pilih folder')->assertSee('webkitdirectory', false)
            ->assertSee('Atau isi butiran draf dahulu')->assertSee('action="'.route('portal.drafts.drop').'"', false);
    }

    public function test_drop_creates_draft_and_redirects(): void
    {
        $owner = $this->applicant();
        $response = $this->actingAs($owner)->post(route('portal.drafts.drop'), ['application_type_id' => 'TEL', 'files' => [$this->pdf('geran.pdf')]]);

        $draft = ApplicantDraft::query()->sole();
        $response->assertRedirect(route('portal.drafts.edit', $draft).'#dokumen');
        $this->assertTrue($draft->title_provisional);
        $this->assertSame($owner->id, $draft->user_id);
        $this->assertSame(1, ApplicantDraftDocument::query()->whereNull('item_code')->count());
    }

    public function test_drop_requires_a_type_and_files(): void
    {
        $this->actingAs($this->applicant())->post(route('portal.drafts.drop'), ['application_type_id' => 'TEL'])->assertSessionHasErrors('files');
        $this->actingAs($this->applicant('b@example.test'))->post(route('portal.drafts.drop'), ['files' => [$this->pdf('a.pdf')]])->assertSessionHasErrors('application_type_id');
        $this->assertSame(0, ApplicantDraft::query()->count());
    }

    public function test_drop_json_returns_add_url(): void
    {
        $response = $this->actingAs($this->applicant())->postJson(route('portal.drafts.drop'), ['application_type_id' => 'TEL',
            'files' => [$this->pdf('geran.pdf'), UploadedFile::fake()->createWithContent('nota.pdf', 'bukan pdf')]]);

        $draft = ApplicantDraft::query()->sole();
        $response->assertOk()->assertJson([
            'draft_url' => route('portal.drafts.edit', $draft).'#dokumen',
            'add_url' => route('portal.drafts.documents.add', $draft),
            'accepted' => ['geran.pdf'],
        ]);
        $this->assertSame('nota.pdf', $response->json('rejected.0.name'));
    }

    public function test_add_to_existing_draft(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->actingAs($owner)->post(route('portal.drafts.documents.add', $draft), ['files' => [$this->pdf('a.pdf', 'a'), $this->pdf('b.pdf', 'b')]])
            ->assertRedirect(route('portal.drafts.edit', $draft).'#dokumen');

        $this->assertSame(2, ApplicantDraftDocument::query()->where('draft_id', $draft->id)->whereNull('item_code')->count());
    }

    public function test_rejected_files_are_listed_after_redirect(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->actingAs($owner)->followingRedirects()->post(route('portal.drafts.documents.add', $draft), ['files' => [UploadedFile::fake()->createWithContent('palsu.pdf', '<html></html>')]])
            ->assertOk()->assertSee('palsu.pdf')->assertSee('Fail tidak diterima');
    }

    public function test_tray_lists_unsorted_file_with_item_choices(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $file = $this->tray($draft, 'imbasan-001.pdf');

        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Fail belum dipadankan')->assertDontSee('Belum diisih')->assertSee('imbasan-001.pdf')->assertSee('Geran tanah')
            ->assertSeeInOrder(['<optgroup label="Wajib">', 'Geran tanah</option>', '<optgroup label="Pilihan">', 'Gambar tapak</option>'], false)
            ->assertSee(route('portal.drafts.documents.assign', [$draft, $file]), false)
            ->assertSee(route('portal.drafts.documents.remove', [$draft, $file]), false)
            ->assertDontSee('Pelan struktur</option>', false);
    }

    public function test_checklist_is_split_into_wajib_and_pilihan(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);

        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSeeInOrder(['precheck-group-title">Wajib</h3>', 'Geran tanah', 'Pelan lantai', 'precheck-group-title">Pilihan</h3>', 'Gambar tapak'], false);
    }

    public function test_pending_tray_file_keeps_the_checklist_polling(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->tray($draft, 'baru.pdf', ['precheck_status' => 'pending', 'readable' => null]);

        $this->actingAs($owner)->get(route('portal.drafts.checklist', $draft))->assertOk()->assertSee('data-precheck-pending', false);
    }

    public function test_tray_explains_when_automatic_reading_was_unavailable(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->tray($draft, 'imbasan.pdf', ['ocr_unavailable' => true]);

        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Bacaan automatik tidak tersedia untuk sebahagian fail — sila padankan fail tersebut sendiri.');
    }

    public function test_assign_places_file_and_rejects_oversize(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $file = $this->tray($draft);

        DocumentType::query()->where('code', 'bina_tel_geran')->update(['max_file_size_kb' => 0]);
        $this->actingAs($owner)->post(route('portal.drafts.documents.assign', [$draft, $file]), ['item_code' => 'geran'])->assertSessionHasErrors('file');
        $this->assertNull($file->fresh()->item_code);

        DocumentType::query()->where('code', 'bina_tel_geran')->update(['max_file_size_kb' => 20480]);
        $this->actingAs($owner)->post(route('portal.drafts.documents.assign', [$draft, $file]), ['item_code' => 'geran'])
            ->assertRedirect(route('portal.drafts.edit', $draft).'#dokumen');
        $this->assertSame('geran', $file->fresh()->item_code);
        $this->assertSame('manual', $file->fresh()->sort_source);
    }

    public function test_assign_refuses_item_that_does_not_apply(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $file = $this->tray($draft);

        $this->actingAs($owner)->post(route('portal.drafts.documents.assign', [$draft, $file]), ['item_code' => 'pelan_struktur'])->assertSessionHasErrors('file');
        $this->assertNull($file->fresh()->item_code);
    }

    public function test_remove_deletes_only_tray_files(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $file = $this->tray($draft);
        $placed = $this->draftDocument($draft, 'geran');

        $this->actingAs($owner)->delete(route('portal.drafts.documents.remove', [$draft, $placed]))->assertSessionHasErrors('file');
        $this->assertNotNull($placed->fresh());

        $this->actingAs($owner)->delete(route('portal.drafts.documents.remove', [$draft, $file]))->assertRedirect(route('portal.drafts.edit', $draft).'#dokumen');
        $this->assertNull($file->fresh());
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_foreign_draft_is_not_found(): void
    {
        $draft = $this->telDraft($this->applicant());
        $file = $this->tray($draft);
        $other = $this->applicant('lain@example.test');

        $this->actingAs($other)->post(route('portal.drafts.documents.add', $draft), ['files' => [$this->pdf('a.pdf')]])->assertNotFound();
        $this->actingAs($other)->post(route('portal.drafts.documents.assign', [$draft, $file]), ['item_code' => 'geran'])->assertNotFound();
        $this->actingAs($other)->delete(route('portal.drafts.documents.remove', [$draft, $file]))->assertNotFound();
    }

    public function test_saving_details_clears_provisional_title(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $draft->forceFill(['title_provisional' => true, 'title' => 'Draf baharu — 16/11/2026'])->save();

        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()->assertSee('Tajuk sementara')
            ->assertDontSee('value="Draf baharu — 16/11/2026"', false);
        $this->actingAs($owner)->put(route('portal.drafts.update', $draft), ['application_type_id' => 'TEL', 'title' => 'Menara 30 m', 'version' => 1])
            ->assertRedirect();
        $this->assertFalse($draft->fresh()->title_provisional);
    }

    public function test_unsafe_zip_on_first_drop_returns_the_draft_with_the_reason(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid().'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('../evil.pdf', '%PDF-1.4
%%EOF
');
        $zip->close();

        $response = $this->actingAs($this->applicant())->postJson(route('portal.drafts.drop'), ['application_type_id' => 'TEL',
            'files' => [new UploadedFile($path, 'pakej.zip', 'application/zip', null, true)]]);
        @unlink($path);

        $draft = ApplicantDraft::query()->sole();
        $response->assertOk()->assertJson(['draft_url' => route('portal.drafts.edit', $draft).'#dokumen', 'accepted' => []]);
        $this->assertSame('pakej.zip', $response->json('rejected.0.name'));
        $this->assertSame(0, ApplicantDraftDocument::query()->count());
    }

    public function test_removing_a_stale_item_file_clears_its_older_versions(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner, ['storeys' => 4]);
        $this->draftDocument($draft, 'pelan_struktur');
        $latest = $this->draftDocument($draft, 'pelan_struktur');
        $draft->forceFill(['storeys' => 1])->save();

        $this->actingAs($owner)->delete(route('portal.drafts.documents.remove', [$draft, $latest]))->assertRedirect();

        $this->assertSame([], app(DraftChecklist::class)->for($draft->fresh())['unsorted']);
    }

    public function test_placing_a_stale_item_file_clears_its_older_versions(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner, ['storeys' => 4]);
        $this->draftDocument($draft, 'pelan_struktur');
        $latest = $this->draftDocument($draft, 'pelan_struktur');
        Storage::disk('local')->put($latest->path, '%PDF-1.4
%%EOF
');
        $draft->forceFill(['storeys' => 1])->save();

        $this->actingAs($owner)->post(route('portal.drafts.documents.assign', [$draft, $latest]), ['item_code' => 'pelan_lantai'])->assertRedirect();

        $this->assertSame([], app(DraftChecklist::class)->for($draft->fresh())['unsorted']);
        $this->assertSame('pelan_lantai', $latest->fresh()->item_code);
    }

    public function test_drop_refuses_a_type_without_a_published_checklist(): void
    {
        $type = ApplicationType::query()->portal()->pluck('id')
            ->first(fn ($id) => ! DB::table('core.document_catalogues')->where('application_type_id', $id)->exists());
        $this->assertNotNull($type, 'Test needs a portal type without a catalogue.');

        $this->actingAs($this->applicant())->post(route('portal.drafts.drop'), ['application_type_id' => $type, 'files' => [$this->pdf('a.pdf')]])
            ->assertSessionHasErrors('application_type_id');
        $this->assertSame(0, ApplicantDraft::query()->count());
    }

    public function test_unscanned_file_cannot_be_downloaded_or_placed(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $file = $this->tray($draft, 'baru.pdf', ['precheck_status' => 'pending', 'scan_pending' => true]);

        $this->actingAs($owner)->get(route('portal.drafts.documents.download', [$draft, $file]))->assertNotFound();
        $this->actingAs($owner)->post(route('portal.drafts.documents.assign', [$draft, $file]), ['item_code' => 'geran'])->assertSessionHasErrors('file');
        $this->assertNull($file->fresh()->item_code);
    }

    public function test_tray_shows_why_a_file_was_rejected(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $file = $this->tray($draft, 'virus.pdf', ['rejected_reason' => 'Dokumen ditolak oleh imbasan keselamatan.']);

        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('virus.pdf')->assertSee('Dokumen ditolak oleh imbasan keselamatan.')
            ->assertDontSee(route('portal.drafts.documents.assign', [$draft, $file]), false);
        $this->actingAs($owner)->get(route('portal.drafts.documents.download', [$draft, $file]))->assertNotFound();
    }
}
