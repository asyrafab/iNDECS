<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ProfileFieldReview;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\MatrixProfileProjection;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProfileFieldReviewTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    private Application $application;

    private ReviewVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
        [$this->application] = $this->paidLjup();
        $this->version = ReviewVersion::query()->sole();
        $profile = ApplicationProfile::query()->where('review_version_id', $this->version->id)->sole();
        $profile->forceFill(['profile_json' => array_merge($profile->profile_json ?? [], [
            'project_title' => 'Cadangan Taman Uji', 'developer' => 'Pemaju Uji Sdn Bhd',
            'field_evidence' => [['field' => 'project_title', 'page' => 1]],
        ])])->save();
    }

    private function review(User $officer, array $data)
    {
        return $this->actingAs($officer)->post(route('kejuruteraan.fields.review', $this->application),
            $data + ['review_version_id' => $this->version->id, 'reason' => 'Disemak dengan pelan tapak.']);
    }

    public function test_officer_corrects_a_field_with_reason(): void
    {
        $officer = $this->engineer();
        $title = $this->application->title;
        $this->review($officer, ['field' => 'developer', 'disposition' => 'CORRECTED', 'value' => 'Pemaju Betul Sdn Bhd', 'expected_revision' => 0])
            ->assertRedirect()->assertSessionHasNoErrors();

        $row = ProfileFieldReview::query()->sole();
        $this->assertSame(1, $row->revision);
        $this->assertSame($officer->id, $row->actor_id);
        $this->assertSame('Pemaju Betul Sdn Bhd', $row->value);
        $this->assertSame($title, $this->application->fresh()->title);
        $this->actingAs($officer)->get(route('kejuruteraan.show', $this->application))->assertSee('Pemaju Betul Sdn Bhd');
    }

    public function test_stale_revision_is_refused(): void
    {
        $officer = $this->engineer();
        $this->review($officer, ['field' => 'developer', 'disposition' => 'CORRECTED', 'value' => 'A Sdn Bhd', 'expected_revision' => 0]);
        $this->review($this->engineer(), ['field' => 'developer', 'disposition' => 'CORRECTED', 'value' => 'B Sdn Bhd', 'expected_revision' => 0])->assertStatus(409);
        $this->assertSame(1, ProfileFieldReview::query()->count());
    }

    public function test_accept_requires_extracted_value_and_evidence(): void
    {
        $officer = $this->engineer();
        $this->review($officer, ['field' => 'developer', 'disposition' => 'ACCEPTED', 'expected_revision' => 0])->assertStatus(422); // no field evidence
        $this->review($officer, ['field' => 'consultant', 'disposition' => 'ACCEPTED', 'expected_revision' => 0])->assertStatus(422); // no value
        $this->review($officer, ['field' => 'project_title', 'disposition' => 'ACCEPTED', 'expected_revision' => 0])->assertRedirect();
        $this->assertSame('Cadangan Taman Uji', ProfileFieldReview::query()->sole()->value);
    }

    public function test_corrected_value_cannot_be_blank_and_field_must_be_known(): void
    {
        $officer = $this->engineer();
        $this->review($officer, ['field' => 'developer', 'disposition' => 'CORRECTED', 'value' => '  ', 'expected_revision' => 0])->assertStatus(422);
        $this->review($officer, ['field' => 'password', 'disposition' => 'REJECTED', 'expected_revision' => 0])->assertSessionHasErrors('field');
    }

    public function test_projection_uses_latest_field_review(): void
    {
        $this->review($this->engineer(), ['field' => 'developer', 'disposition' => 'CORRECTED', 'value' => 'Pemaju Betul', 'expected_revision' => 0]);
        $report = $this->version->reports()->latest()->first()->report_json;
        $projected = app(MatrixProfileProjection::class)->apply($report);
        $this->assertSame('Pemaju Betul', $projected['application_profile']['developer']);
        $this->assertTrue($projected['profile_dependencies_stale']);
    }

    public function test_field_reviews_are_append_only(): void
    {
        $this->review($this->engineer(), ['field' => 'developer', 'disposition' => 'REJECTED', 'expected_revision' => 0]);
        $this->expectException(QueryException::class);
        DB::table('airs.profile_field_reviews')->delete();
    }

    public function test_building_officer_cannot_review_fields(): void
    {
        $builder = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        $this->review($builder, ['field' => 'developer', 'disposition' => 'REJECTED', 'expected_revision' => 0])->assertForbidden();
    }
}
