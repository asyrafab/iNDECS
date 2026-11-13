<?php

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Contracts\TextExtractor;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Services\Precheck\ExtractedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalPrecheckPagesTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    private const CONTRACT = 'Semakan ini membantu menyediakan dokumen sebelum penyerahan. Kandungan teknikal dan pematuhan akan disemak dalam proses rasmi.';

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

    public function test_owner_sees_items_labels_and_contract_sentence(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Dokumen diperlukan')->assertSee('0/2 dokumen wajib sedia')
            ->assertSee('Geran tanah')->assertSee('Belum dilampirkan')->assertSee(self::CONTRACT)
            ->assertSee('Butiran projek')->assertSee('name="storeys"', false)
            ->assertSee('Bayar semakan AIRS')->assertSee('Lengkapkan dokumen wajib untuk meneruskan');
    }

    public function test_upload_then_confirm_flow_through_routes(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->actingAs($owner)->post(route('portal.drafts.documents.store', [$draft, 'geran']), ['file' => $this->pdf()])
            ->assertRedirect(route('portal.drafts.edit', $draft).'#dokumen');
        Queue::assertPushed(PrecheckDraftDocument::class);
        $doc = ApplicantDraftDocument::query()->firstOrFail();
        $this->get(route('portal.drafts.edit', $draft))->assertSee('Sedang disemak')->assertSee('data-precheck-pending', false);

        $this->app->instance(TextExtractor::class, new class implements TextExtractor
        {
            public function extract(string $absolutePath, string $mime): ExtractedText
            {
                return new ExtractedText(str_repeat('Dokumen tanpa kata kunci ', 3));
            }
        });
        (new PrecheckDraftDocument($doc->id))->handle(app(TextExtractor::class));
        $this->get(route('portal.drafts.checklist', $draft))->assertOk()->assertSee('Perlu pengesahan pemohon')->assertSee('Saya sahkan dokumen ini betul')
            ->assertDontSee('data-precheck-pending', false);
        $this->post(route('portal.drafts.documents.confirm', [$draft, $doc]))->assertRedirect(route('portal.drafts.edit', $draft).'#dokumen');
        $this->get(route('portal.drafts.edit', $draft))->assertSee('1/2 dokumen wajib sedia');
    }

    public function test_other_applicant_gets_404_on_upload_download_confirm_and_checklist(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $doc = $this->draftDocument($draft, 'geran', ['matched_keywords' => []]);
        $this->actingAs($this->applicant('lain@example.test'));
        $this->post(route('portal.drafts.documents.store', [$draft, 'geran']), ['file' => $this->pdf()])->assertNotFound();
        $this->get(route('portal.drafts.documents.download', [$draft, $doc]))->assertNotFound();
        $this->post(route('portal.drafts.documents.confirm', [$draft, $doc]))->assertNotFound();
        $this->get(route('portal.drafts.checklist', $draft))->assertNotFound();
        $this->assertNull($doc->fresh()->confirmed_at);
    }

    public function test_document_of_another_draft_is_404_even_for_its_owner(): void
    {
        $owner = $this->applicant();
        $draftA = $this->telDraft($owner);
        $draftB = $this->telDraft($owner);
        $doc = $this->draftDocument($draftB, 'geran');
        $this->actingAs($owner)->get(route('portal.drafts.documents.download', [$draftA, $doc]))->assertNotFound();
    }

    public function test_download_is_attachment_nosniff_owner_only(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->actingAs($owner)->post(route('portal.drafts.documents.store', [$draft, 'pelan_lantai']), ['file' => $this->pdf()]);
        $doc = ApplicantDraftDocument::query()->firstOrFail();
        $response = $this->get(route('portal.drafts.documents.download', [$draft, $doc]))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_unavailable_catalogue_message_for_pkm(): void
    {
        $owner = $this->applicant();
        $draft = new ApplicantDraft(['application_type_id' => 'PKM', 'title' => 'PKM']);
        $draft->user_id = $owner->id;
        $draft->version = 1;
        $draft->save();
        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Senarai semak belum tersedia untuk jenis permohonan ini.')->assertDontSee('Butiran projek');
    }

    public function test_no_payment_or_application_side_effects(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->actingAs($owner)->post(route('portal.drafts.documents.store', [$draft, 'geran']), ['file' => $this->pdf()]);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('core.accepted_submissions', 0);
        $this->assertDatabaseCount('airs.review_versions', 0);
    }

    public function test_labels_never_use_pass_fail_wording(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->draftDocument($draft, 'geran', ['readable' => false]);
        $html = $this->actingAs($owner)->get(route('portal.drafts.checklist', $draft))->assertOk()->getContent();
        foreach (['Lulus', 'Gagal', 'Skor', 'Ditolak'] as $word) {
            $this->assertStringNotContainsString($word, $html);
        }
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('dokumen.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }
}
