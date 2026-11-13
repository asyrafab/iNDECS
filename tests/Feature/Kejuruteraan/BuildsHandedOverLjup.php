<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Contracts\DocumentProcessor;
use App\Models\ApplicantDraft;
use App\Models\Application;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\PaidCheck\BuildsLjupJourney;
use Tests\Support\FakeAirsDocumentProcessor;

/** A paid LJUP application whose check 1 completed and was handed over to JKEJ. Call setUpLjupOfficerWorld() in setUp. */
trait BuildsHandedOverLjup
{
    use BuildsLjupJourney;

    protected function setUpLjupOfficerWorld(): void
    {
        $this->seed();
        Storage::fake('local');
        $this->app->instance(DocumentProcessor::class, new FakeAirsDocumentProcessor);
        $this->seedLjupCatalogue();
        config(['airs.fee_sen' => 15000]);
    }

    protected function engineer(string $role = 'engineering_officer', ?string $district = null): User
    {
        return User::factory()->create(['department_id' => 'JKEJ', 'role' => $role, 'district' => $district]);
    }

    /** @return array{0: Application, 1: PaidCheckJourney} */
    protected function paidLjup(?ApplicantDraft $draft = null, bool $handOver = true): array
    {
        $draft ??= $this->readyLjupDraft($this->applicant('psp'.uniqid().'@example.test'));
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-'.$draft->id, CarbonImmutable::now()->subMinute());
        $journey = PaidCheckJourney::query()->where('draft_id', $draft->id)->sole();
        if ($handOver) {
            app(PaidCheckJourneyService::class)->submitAsIs($owner, $journey);
        }
        $journey->refresh();

        return [$journey->application->fresh(), $journey];
    }
}
