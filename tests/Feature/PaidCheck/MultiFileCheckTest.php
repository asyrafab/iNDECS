<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Contracts\DocumentScanner;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicationDocument;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\DocumentCatalogueService;
use App\Services\PaidCheck\CheckPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

/** Every file of a multi-file item reaches the paid AIRS check, and the amendment keeps, adds and withdraws within it. */
final class MultiFileCheckTest extends TestCase
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
        $engineer = User::factory()->create(['department_id' => 'JKEJ', 'role' => 'senior_engineering_officer']);
        app(DocumentCatalogueService::class)->publish('LJUP', ['version' => 0, 'source_reference' => 'Ujian sahaja', 'items' => [
            ['code' => 'pelan_lampu', 'name' => 'Pelan Lampu Jalan', 'is_required' => true],
            ['code' => 'laporan_fotometrik', 'name' => 'Laporan Fotometrik', 'is_required' => true],
            ['code' => 'gambar_tapak', 'name' => 'Gambar Tapak', 'is_required' => false, 'max_files' => 5],
        ]], $engineer);
        config(['airs.fee_sen' => 15000]);
    }

    /** @return array{0: ApplicantDraft, 1: User, 2: PaidCheckJourney} */
    private function paidWithPhotos(array $names): array
    {
        $draft = $this->readyLjupDraft();
        foreach ($names as $name) {
            $this->ljupUpload($draft, 'gambar_tapak', null, ['original_name' => $name]);
        }
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-'.$draft->id, CarbonImmutable::now()->subMinute());

        return [$draft->fresh(), $owner, PaidCheckJourney::query()->where('draft_id', $draft->id)->sole()];
    }

    private function currentPhotos(PaidCheckJourney $journey): array
    {
        return ApplicationDocument::query()->where('application_id', $journey->application_id)->where('is_current', true)
            ->where('original_name', 'like', 'foto-%')->orderBy('original_name')->pluck('original_name')->all();
    }

    public function test_paid_check_receives_every_file_of_a_multi_item(): void
    {
        [, , $journey] = $this->paidWithPhotos(['foto-a.pdf', 'foto-b.pdf', 'foto-c.pdf']);

        $this->assertSame(['foto-a.pdf', 'foto-b.pdf', 'foto-c.pdf'], $this->currentPhotos($journey));
        $this->assertSame(5, ApplicationDocument::query()->where('application_id', $journey->application_id)->where('is_current', true)->count());
    }

    public function test_amendment_keeps_adds_and_withdraws_within_multi_item(): void
    {
        [$draft, $owner, $journey] = $this->paidWithPhotos(['foto-a.pdf', 'foto-b.pdf', 'foto-c.pdf']);
        $kept = ApplicationDocument::query()->where('application_id', $journey->application_id)->where('original_name', 'foto-a.pdf')->sole();
        \App\Models\ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('original_name', 'foto-b.pdf')->delete();
        $this->ljupUpload($draft, 'gambar_tapak', null, ['original_name' => 'foto-d.pdf']);

        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft), ['confirm_amendment' => '1'])->assertRedirect();

        $this->assertSame(['foto-a.pdf', 'foto-c.pdf', 'foto-d.pdf'], $this->currentPhotos($journey));
        $this->assertTrue($kept->fresh()->is_current, 'An unchanged file keeps its application document.');
        $this->assertSame(0, ApplicationDocument::query()->where('application_id', $journey->application_id)
            ->where('original_name', 'like', 'foto-%')->whereNotNull('supersedes_id')->count());
    }
}
