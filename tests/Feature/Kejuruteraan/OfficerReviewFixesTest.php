<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Knowledge\LessonExporter;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\ProfileFieldReviewService;
use App\Models\CheckPayment;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\Kejuruteraan\OfficerCheckPresenter;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Final-review fixes for the officer workspace branch. */
final class OfficerReviewFixesTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    private const REASON = 'Pengecualian fi di bawah dasar MBSP/JKEJ/2026/01 (projek awam).';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    private function correctedVersion(string $field, string $aiValue, string $officerValue): ReviewVersion
    {
        [, $journey] = $this->paidLjup();
        $version = ReviewVersion::query()->sole();
        $profile = ApplicationProfile::query()->where('review_version_id', $version->id)->sole();
        $profile->forceFill(['profile_json' => array_merge($profile->profile_json ?? [], [$field => $aiValue])])->save();
        app(ProfileFieldReviewService::class)->record($this->engineer(), $version, $field, 'CORRECTED', $officerValue, 'Disemak.', 0);

        return $version;
    }

    public function test_identity_field_values_are_not_exported(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'airs-lessons-'.uniqid();
        config(['airs.matrix.lessons_path' => $path]);
        $this->correctedVersion('developer', 'Pemaju ABC Sdn Bhd', 'Pemaju ABD Sdn Bhd');

        $file = app(LessonExporter::class)->export()['file'];
        $text = (string) file_get_contents($file);
        $lesson = json_decode(trim($text), true);

        $this->assertStringNotContainsString('Pemaju', $text);
        $this->assertNull($lesson['ai_value']);
        $this->assertNull($lesson['officer_value']);
        $this->assertTrue($lesson['value_redacted']);
        $this->assertSame('developer', $lesson['field']);
        array_map('unlink', glob($path.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($path);
    }

    public function test_waiver_requires_a_ready_draft(): void
    {
        $senior = $this->engineer('senior_engineering_officer');
        $empty = $this->ljupDraft($this->applicant('empty@example.test'));
        $noTypes = $this->readyLjupDraft($this->applicant('notypes@example.test'));
        $noTypes->forceFill(['lighting_types' => []])->save();
        $unreadable = $this->ljupDraft($this->applicant('unreadable@example.test'));
        $this->ljupUpload($unreadable, 'pelan_lampu', null, ['readable' => false]);

        foreach ([$empty, $noTypes, $unreadable] as $draft) {
            try {
                app(CheckPaymentService::class)->waive($senior, $draft, self::REASON);
                $this->fail('Waiver should be refused for draft '.$draft->id);
            } catch (ValidationException) {
            }
        }
        $this->assertSame(0, CheckPayment::query()->count());
    }

    public function test_paid_draft_without_journey_is_listed_as_stuck(): void
    {
        $draft = $this->readyLjupDraft();
        $payment = app(CheckPaymentService::class)->start(User::query()->findOrFail($draft->user_id), $draft, true);
        $payment->forceFill(['status' => 'paid', 'gateway_event_id' => 'evt-x', 'paid_at' => now()->subHours(2)])->save();
        $payment->forceFill(['updated_at' => now()->subHours(2)])->save();

        $stuck = app(PaidCheckJourneyService::class)->stuckPayments();

        $this->assertSame([$payment->id], array_map(fn ($payment) => $payment->id, $stuck));
        $this->artisan('paid-check:stuck')->expectsOutputToContain($payment->reference)->assertSuccessful();
    }

    public function test_officer_score_is_deferred_after_profile_correction(): void
    {
        $this->correctedVersion('project_title', 'Tajuk asal', 'Tajuk betul');
        $application = PaidCheckJourney::query()->sole()->application;

        $check = app(OfficerCheckPresenter::class)->for($application)['checks'][0];

        $this->assertTrue($check['profile_stale']);
        $this->assertNull($check['officer_score']);
        $this->actingAs($this->engineer())->get(route('kejuruteraan.show', $application))->assertOk()->assertSee('Skor ditangguhkan');
    }

    public function test_ljup_permissions_follow_the_officers_own_department(): void
    {
        [$application] = $this->paidLjup();
        $misassigned = User::factory()->create(['department_id' => 'JBANG', 'role' => 'engineering_officer']);
        $this->assertFalse($misassigned->canAccessKejuruteraan());
        $this->actingAs($misassigned)->get(route('kejuruteraan.show', $application))->assertForbidden();
    }
}
