<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Models\ReviewVersion;
use App\Contracts\DocumentScanner;
use App\Enums\PaidCheckState;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicationDocument;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\Precheck\DraftDocumentService;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

final class AmendmentTest extends TestCase
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

    private function paid(?callable $beforeCheck = null): array
    {
        $draft = $this->readyLjupDraft();
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        if ($beforeCheck) {
            $beforeCheck();
        }
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-'.$draft->id, CarbonImmutable::now()->subMinute());

        return [$draft->fresh(), $owner, PaidCheckJourney::query()->where('draft_id', $draft->id)->sole()];
    }

    private function pdf(string $marker): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pelan.pdf', "%PDF-1.4\n% {$marker}\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    public function test_draft_is_locked_while_check_runs(): void
    {
        Queue::fake([PrecheckDraftDocument::class, \App\Airs\Jobs\RunAirsCheck::class]);
        [$draft, $owner, $journey] = $this->paid();
        $this->assertSame(PaidCheckState::Check1Running, $journey->state);
        $before = $draft->documents()->count();

        try {
            app(DraftDocumentService::class)->upload($owner, $draft, 'pelan_lampu', $this->pdf('locked'));
            $this->fail('Upload should be refused while the check runs.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $draft->documents()->count());
        $this->actingAs($owner)->put(route('portal.drafts.update', $draft), ['application_type_id' => 'LJUP', 'title' => 'Tukar', 'version' => $draft->version,
            'lighting_types' => ['BESI']])->assertForbidden();
    }

    public function test_draft_unlocks_during_amendment_window(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->state);
        $document = app(DraftDocumentService::class)->upload($owner, $draft, 'pelan_lampu', $this->pdf('amended'));
        $this->assertNotNull($document->id);
    }

    public function test_extension_adds_five_working_days_once(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $due = $journey->amendment_due_at;
        $this->actingAs($owner)->post(route('portal.drafts.check.extend', $draft))->assertRedirect();
        $journey->refresh();
        $this->assertTrue(app(WorkingCalendar::class)->addWorkingDays($due, 5)->equalTo($journey->amendment_due_at));
        $this->assertNotNull($journey->extended_at);

        $this->actingAs($owner)->post(route('portal.drafts.check.extend', $draft))->assertSessionHasErrors('check');
        $this->assertTrue($journey->amendment_due_at->equalTo($journey->fresh()->amendment_due_at));
    }

    public function test_amendment_creates_revisions_and_runs_check_two(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $oldPlan = ApplicationDocument::query()->where('application_id', $journey->application_id)
            ->where('original_name', 'pelan_lampu.pdf')->sole();
        $newUpload = $this->ljupUpload($draft, 'pelan_lampu');

        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft))->assertRedirect();

        $journey->refresh();
        $this->assertNotNull($journey->amended_at);
        $this->assertFalse($oldPlan->fresh()->is_current);
        $revision = ApplicationDocument::query()->where('supersedes_id', $oldPlan->id)->sole();
        $this->assertSame($newUpload->sha256, $revision->checksum_sha256);
        $this->assertSame(2, $revision->revision_number);
        $this->assertTrue($revision->is_current);
        $this->assertSame(2, ReviewVersion::query()->where('check_number', 2)->sole()->check_number);
        // Check 2 completes synchronously and the journey hands over (Task 7 completes the handover record).
        $this->assertSame(PaidCheckState::HandedOver, $journey->state);
        $this->assertSame(2, $journey->final_check_number);
    }

    public function test_unchanged_documents_are_not_revised(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft));
        $this->assertSame(0, ApplicationDocument::query()->whereNotNull('supersedes_id')->count());
        $this->assertSame(2, ApplicationDocument::query()->where('application_id', $journey->application_id)->where('is_current', true)->count());
    }

    public function test_second_amendment_is_refused(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft));
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft))->assertSessionHasErrors('check');
        $this->assertSame(2, ReviewVersion::query()->count());
    }

    public function test_amendment_can_change_lighting_types(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $draft->forceFill(['lighting_types' => ['BESI', 'KONKRIT']])->save();
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft));
        $this->assertSame(['BESI', 'KONKRIT'], $journey->fresh()->lighting_types);
        $this->assertSame(['BESI', 'KONKRIT'], ReviewVersion::query()->where('check_number', 2)->sole()->reviewCase->selected_lighting_types);
    }

    public function test_removed_item_is_withdrawn_from_the_application(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');
        $this->ljupUpload($draft, 'laporan_fotometrik');
        $photo = $this->ljupUpload($draft, 'gambar_tapak');
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-x', CarbonImmutable::now()->subMinute());
        $journey = PaidCheckJourney::query()->sole();
        $photo->delete();

        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft));

        $this->assertFalse(ApplicationDocument::query()->where('checksum_sha256', $photo->sha256)->sole()->is_current);
    }

    public function test_submit_as_is_hands_over_with_check_one_final(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $this->actingAs($owner)->post(route('portal.drafts.check.submit', $draft))->assertRedirect();
        $journey->refresh();
        $this->assertSame(PaidCheckState::HandedOver, $journey->state);
        $this->assertSame(1, $journey->final_check_number);
        $this->assertSame(0, ReviewVersion::query()->where('check_number', 2)->count());
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft))->assertSessionHasErrors('check');
    }

    public function test_other_user_cannot_act_on_the_journey(): void
    {
        [$draft] = $this->paid();
        $stranger = $this->applicant('stranger@example.test');
        foreach (['extend', 'amend', 'submit'] as $action) {
            $this->actingAs($stranger)->post(route('portal.drafts.check.'.$action, $draft))->assertNotFound();
        }
    }
}
