<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Models\ReviewEvidence;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewFindingFeedback;
use App\Airs\Models\ReviewVersion;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FindingFeedbackTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    private function manualFinding(Application $application, int $check = 1): ReviewFinding
    {
        return ReviewFinding::query()->whereIn('status', ['PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI'])
            ->whereHas('reviewVersion', fn ($q) => $q->where('check_number', $check)->whereHas('reviewCase', fn ($c) => $c->where('application_id', $application->id)))
            ->firstOrFail();
    }

    private function feedback(User $officer, Application $application, ReviewFinding $finding, string $status, ?string $comment = 'Disemak terhadap pelan.')
    {
        return $this->actingAs($officer)->post(route('kejuruteraan.findings.feedback', [$application, $finding]), ['officer_status' => $status, 'officer_comment' => $comment]);
    }

    public function test_officer_comment_is_attributed_and_keeps_ai_finding(): void
    {
        [$application] = $this->paidLjup();
        $finding = $this->manualFinding($application);
        $officer = $this->engineer();

        $this->feedback($officer, $application, $finding, 'BELUM_DITUNAIKAN')->assertRedirect();

        $feedback = ReviewFindingFeedback::query()->sole();
        $this->assertSame($officer->id, $feedback->actor_id);
        $this->assertSame('BELUM_DITUNAIKAN', $feedback->status->value);
        $this->assertSame($finding->status, $finding->fresh()->status);
        $this->actingAs($officer)->get(route('kejuruteraan.show', $application))->assertOk()
            ->assertSee('Pegawai: Belum ditunaikan')->assertSee('Disemak terhadap pelan.')->assertSee($officer->name);
    }

    public function test_ditunaikan_requires_evidence(): void
    {
        [$application] = $this->paidLjup();
        $finding = $this->manualFinding($application);
        ReviewEvidence::query()->where('review_finding_id', $finding->id)->delete();
        $this->feedback($this->engineer(), $application, $finding, 'DITUNAIKAN')->assertSessionHasErrors('feedback');
        $this->assertSame(0, ReviewFindingFeedback::query()->count());
    }

    public function test_comments_only_on_final_check_of_handed_over_journey(): void
    {
        [$application] = $this->paidLjup(null, false);
        $this->feedback($this->engineer(), $application, $this->manualFinding($application), 'BELUM_DITUNAIKAN')->assertSessionHasErrors('feedback');

        // Check 2 final: comments on check 1 are refused.
        $owner = $this->applicant('psp-two@example.test');
        $draft = $this->readyLjupDraft($owner);
        [$second, $journey] = $this->paidLjup($draft, false);
        $this->ljupUpload($draft, 'pelan_lampu');
        app(\App\Services\PaidCheck\PaidCheckJourneyService::class)->submitAmendment($owner, $journey);
        $this->assertSame(2, $journey->fresh()->final_check_number);
        $this->feedback($this->engineer(), $second, $this->manualFinding($second, 1), 'BELUM_DITUNAIKAN')->assertSessionHasErrors('feedback');
        $this->feedback($this->engineer(), $second, $this->manualFinding($second, 2), 'BELUM_DITUNAIKAN')->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_feedback_rows_cannot_be_updated_or_deleted(): void
    {
        [$application] = $this->paidLjup();
        $this->feedback($this->engineer(), $application, $this->manualFinding($application), 'BELUM_DITUNAIKAN');
        $this->expectException(QueryException::class);
        DB::table('airs.review_finding_feedbacks')->update(['status' => 'DITUNAIKAN']);
    }

    public function test_officer_score_reflects_latest_feedback(): void
    {
        [$application] = $this->paidLjup();
        $finding = $this->manualFinding($application);
        $officer = $this->engineer();
        $this->feedback($officer, $application, $finding, 'BELUM_DITUNAIKAN');
        $view = app(\App\Services\Kejuruteraan\OfficerCheckPresenter::class)->for($application);
        $check = $view['checks'][0];
        $this->assertLessThan($check['score'], $check['officer_score']);
        $item = collect($check['items'])->firstWhere('finding_id', $finding->id);
        $this->assertSame('BELUM_DITUNAIKAN', $item['officer_status']);
        $this->assertSame($finding->status->value, $item['ai_status']);
    }

    public function test_finding_of_another_application_is_not_found(): void
    {
        [$application] = $this->paidLjup();
        [$other] = $this->paidLjup();
        $this->feedback($this->engineer(), $application, $this->manualFinding($other), 'BELUM_DITUNAIKAN')->assertNotFound();
    }

    public function test_building_officer_cannot_comment(): void
    {
        [$application] = $this->paidLjup();
        $builder = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        $this->feedback($builder, $application, $this->manualFinding($application), 'BELUM_DITUNAIKAN')->assertForbidden();
    }

    public function test_officer_without_review_permission_cannot_comment(): void
    {
        [$application] = $this->paidLjup();
        // Access only: a custom role holding kejuruteraan.ljup.access but not review.
        DB::table('core.roles')->insert(['id' => 'ljup_viewer', 'label' => 'Pemerhati', 'account_type' => 'staff', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('core.role_permissions')->insert(['role_id' => 'ljup_viewer', 'permission_name' => 'kejuruteraan.ljup.access', 'department_id' => 'JKEJ', 'application_type_id' => 'LJUP', 'created_at' => now(), 'updated_at' => now()]);
        $viewer = User::factory()->create(['department_id' => 'JKEJ', 'role' => 'ljup_viewer']);
        $this->feedback($viewer, $application, $this->manualFinding($application), 'BELUM_DITUNAIKAN')->assertForbidden();
    }
}
