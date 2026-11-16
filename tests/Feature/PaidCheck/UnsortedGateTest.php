<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Contracts\DocumentScanner;
use App\Enums\PaidCheckState;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

/** Upload-first part 1, Task 5: nothing in "Belum diisih" may be silently left out of payment or the amendment. */
final class UnsortedGateTest extends TestCase
{
    use BuildsLjupJourney, RefreshDatabase;

    private const TRAY = 'Isih atau buang fail dalam "Belum diisih" sebelum membayar.';

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

    private function tray(ApplicantDraft $draft): ApplicantDraftDocument
    {
        return $this->ljupUpload($draft, 'pelan_lampu', null, ['item_code' => null, 'document_type_id' => null, 'sequence' => 0,
            'path' => "drafts/{$draft->id}/inbox/".uniqid().'.pdf', 'confirmed_at' => null]);
    }

    private function owner(ApplicantDraft $draft): User
    {
        return User::query()->findOrFail($draft->user_id);
    }

    private function refused(callable $action, string $key, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a validation refusal.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()[$key] ?? null);
        }
    }

    public function test_payment_refused_while_tray_not_empty(): void
    {
        $draft = $this->readyLjupDraft();
        $this->tray($draft);

        $this->refused(fn () => app(CheckPaymentService::class)->start($this->owner($draft), $draft, true), 'payment', self::TRAY);
        $this->actingAs($this->owner($draft))->get(route('portal.drafts.edit', $draft))->assertOk()->assertSee(self::TRAY);
    }

    public function test_payment_refused_while_title_provisional(): void
    {
        $draft = $this->readyLjupDraft();
        $draft->forceFill(['title_provisional' => true])->save();

        $this->refused(fn () => app(CheckPaymentService::class)->start($this->owner($draft), $draft, true), 'payment',
            'Isi tajuk cadangan dan simpan draf sebelum membayar.');
    }

    public function test_payment_allowed_once_tray_cleared(): void
    {
        $draft = $this->readyLjupDraft();
        $file = $this->tray($draft);
        $file->delete();

        $payment = app(CheckPaymentService::class)->start($this->owner($draft), $draft, true);
        $this->assertSame('pending', $payment->status);
    }

    public function test_amendment_refused_while_tray_not_empty(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = $this->owner($draft);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-'.$draft->id, CarbonImmutable::now()->subMinute());
        $journey = PaidCheckJourney::query()->where('draft_id', $draft->id)->sole();
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->state);
        $this->tray($draft);

        $this->refused(fn () => app(PaidCheckJourneyService::class)->submitAmendment($owner, $journey), 'check', self::TRAY);
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->fresh()->state);
        $this->assertNull($journey->fresh()->amended_at);
    }
}
