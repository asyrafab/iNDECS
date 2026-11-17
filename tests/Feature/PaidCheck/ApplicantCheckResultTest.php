<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewVersion;
use App\Enums\PaidCheckState;
use App\Models\ApplicantDraft;
use App\Models\ApplicationDocument;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\Portal\PortalBoard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

final class ApplicantCheckResultTest extends TestCase
{
    use BuildsLjupJourney, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->app->instance(DocumentProcessor::class, new FakeAirsDocumentProcessor);
        $this->seedLjupCatalogue();
        config(['airs.fee_sen' => 15000]);
    }

    private function paidDraft(?ApplicantDraft $draft = null): ApplicantDraft
    {
        $draft ??= $this->readyLjupDraft();
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-'.$draft->id, CarbonImmutable::now()->subMinute());

        return $draft->fresh();
    }

    private function owner(ApplicantDraft $draft): User
    {
        return User::query()->findOrFail($draft->user_id);
    }

    public function test_running_check_shows_progress_and_no_score(): void
    {
        Queue::fake();
        $draft = $this->paidDraft();
        $this->actingAs($this->owner($draft))->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Semakan AIRS sedang dijalankan')->assertDontSee('Skor kesediaan')->assertDontSee('Bayar semakan AIRS');
    }

    public function test_completed_check_shows_titles_labels_comments_and_score(): void
    {
        $draft = $this->paidDraft();
        $version = ReviewVersion::query()->sole();
        $finding = ReviewFinding::query()->where('review_version_id', $version->id)->with('rule')->whereIn('status', ['PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI'])->first();
        $this->assertNotNull($finding);

        $this->actingAs($this->owner($draft))->get(route('portal.drafts.check', $draft))->assertOk()
            ->assertSee('Skor kesediaan dokumen')->assertSee((string) $version->score)
            ->assertSee('Akan disemak oleh pegawai')->assertSee('Semakan 1');
    }

    public function test_result_hides_ai_rationale_confidence_and_shadow_outcome(): void
    {
        $draft = $this->paidDraft();
        $version = ReviewVersion::query()->sole();
        $response = $this->actingAs($this->owner($draft))->get(route('portal.drafts.check', $draft))->assertOk();
        $response->assertDontSee('MEETS_THRESHOLD')->assertDontSee('BELOW_THRESHOLD')->assertDontSee('confidence')->assertDontSee('Keyakinan');
        foreach (ReviewFinding::query()->where('review_version_id', $version->id)->pluck('finding') as $rationale) {
            if (mb_strlen((string) $rationale) > 25) {
                $response->assertDontSee((string) $rationale);
            }
        }
    }

    public function test_missing_required_document_is_listed(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');
        $draft = $this->paidDraft($draft);
        $this->actingAs($owner)->get(route('portal.drafts.check', $draft))->assertOk()
            ->assertSee('Dokumen wajib belum dilampirkan')->assertSee('Laporan Fotometrik');
    }

    public function test_evidence_link_streams_own_document(): void
    {
        $draft = $this->paidDraft();
        $document = ApplicationDocument::query()->firstOrFail();
        $response = $this->actingAs($this->owner($draft))->get(route('portal.drafts.check.document', [$draft, $document]));
        $response->assertOk();
        $this->assertSame(Storage::disk('local')->get($document->storage_path), $response->streamedContent());
    }

    public function test_other_users_cannot_see_result_or_documents(): void
    {
        $draft = $this->paidDraft();
        $document = ApplicationDocument::query()->firstOrFail();
        $stranger = $this->applicant('stranger@example.test');
        $this->actingAs($stranger)->get(route('portal.drafts.check', $draft))->assertNotFound();
        $this->actingAs($stranger)->get(route('portal.drafts.check.document', [$draft, $document]))->assertNotFound();

        $otherDraft = $this->paidDraft($this->readyLjupDraft($stranger));
        $this->actingAs($stranger)->get(route('portal.drafts.check.document', [$otherDraft, $document]))->assertNotFound();
    }

    public function test_failed_check_shows_contact_message_and_no_deadline(): void
    {
        $this->app->instance(DocumentProcessor::class, new class implements DocumentProcessor
        {
            public function process(string $filePath, string $outputDirectory): array
            {
                return ['page_count' => 0, 'is_encrypted' => false, 'warnings' => [], 'pages' => []];
            }
        });
        try {
            $draft = $this->paidDraft();
        } catch (\Throwable) {
            $draft = ApplicantDraft::query()->sole();
        }
        $journey = PaidCheckJourney::query()->sole();
        $this->assertSame(PaidCheckState::Check1Running, $journey->state);
        $this->assertNull($journey->amendment_due_at);
        $this->actingAs($this->owner($draft))->get(route('portal.drafts.check', $draft))->assertOk()
            ->assertSee('Pegawai JKEJ akan menghubungi anda')->assertDontSee('Hantar pindaan');
    }

    public function test_portal_board_reminds_applicant_when_result_is_ready(): void
    {
        $draft = $this->paidDraft();
        $board = app(PortalBoard::class)->for($this->owner($draft));
        $this->assertContains($draft->title.': keputusan semakan AIRS sedia', array_column($board['todos'], 'text'));
    }

    public function test_awaiting_applicant_shows_deadline_and_actions(): void
    {
        $draft = $this->paidDraft();
        $this->actingAs($this->owner($draft))->get(route('portal.drafts.check', $draft))->assertOk()
            ->assertSee('Tarikh akhir pindaan')->assertSee('Hantar tanpa pindaan')->assertSee('Lanjutkan 5 hari bekerja');
    }
}
