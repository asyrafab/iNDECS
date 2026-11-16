<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Contracts\DocumentScanner;
use App\Contracts\TextExtractor;
use App\Enums\PaidCheckState;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\Precheck\ExtractedText;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

/** Upload-first part 2, Task 2: LJUP lighting type, and no filling while the draft is locked for the AIRS check. */
final class DraftPrefillLjupTest extends TestCase
{
    use BuildsLjupJourney, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Queue::fake([PrecheckDraftDocument::class]);
        $this->app->instance(DocumentProcessor::class, new FakeAirsDocumentProcessor);
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
        $this->seedLjupCatalogue();
        config(['airs.fee_sen' => 15000]);
    }

    private function check(ApplicantDraft $draft, string $text): void
    {
        $document = $this->ljupUpload($draft, 'pelan_lampu', null, ['item_code' => null, 'document_type_id' => null, 'sequence' => 0,
            'original_name' => 'SURAT PERMOHONAN.pdf', 'path' => "drafts/{$draft->id}/inbox/".uniqid().'.pdf',
            'precheck_status' => 'pending', 'readable' => null, 'confirmed_at' => null]);
        Storage::disk('local')->put($document->path, "%PDF-1.4\n%%EOF\n");
        $fake = new class(new ExtractedText($text)) implements TextExtractor
        {
            public function __construct(private ExtractedText $result) {}

            public function extract(string $absolutePath, string $mime): ExtractedText
            {
                return $this->result;
            }
        };
        (new PrecheckDraftDocument($document->id))->handle($fake);
    }

    public function test_ljup_lighting_type_filled_only_while_empty(): void
    {
        $draft = $this->readyLjupDraft();
        $draft->forceFill(['lighting_types' => []])->save();

        $this->check($draft, 'SPESIFIKASI: TIANG KONKRIT 10M');

        $this->assertSame(['KONKRIT'], $draft->fresh()->lighting_types);
        $this->assertSame('SURAT PERMOHONAN.pdf', $draft->fresh()->prefill_sources['lighting_types']['name']);

        $chosen = $this->readyLjupDraft(User::query()->findOrFail($draft->user_id));
        $chosen->forceFill(['lighting_types' => ['SOLAR']])->save();
        $this->check($chosen, 'SPESIFIKASI: TIANG KONKRIT 10M');
        $this->assertSame(['SOLAR'], $chosen->fresh()->lighting_types);
    }

    public function test_draft_locked_for_the_check_is_not_filled(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-'.$draft->id, CarbonImmutable::now()->subMinute());
        PaidCheckJourney::query()->where('draft_id', $draft->id)->update(['state' => PaidCheckState::Check1Running->value]);
        $draft->forceFill(['title_provisional' => true, 'site_address' => null])->save();
        $version = $draft->fresh()->version;

        $this->check($draft, 'PERMOHONAN BAGI CADANGAN LAMPU JALAN DI ATAS LOT 1. UNTUK TETUAN X');

        $this->assertNull($draft->fresh()->site_address);
        $this->assertSame($version, $draft->fresh()->version);
    }
}
