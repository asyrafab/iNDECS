<?php

declare(strict_types=1);

namespace Tests\Feature\Kejuruteraan;

use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\FindingFeedbackService;
use App\Models\AcceptedSubmission;
use App\Models\ApplicationLetterDraft;
use App\Models\ApplicationRecommendation;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use App\Services\ApplicationRecommendationService;
use App\Services\Kejuruteraan\LjupRecommendationSource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class InternalRecommendationTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    private function input(User $actor, string $id): array
    {
        $context = app(ApplicationRecommendationService::class)->context($actor, $id, app(LjupRecommendationSource::class));

        return ['expected_revision' => $context['revision'], 'source_hash' => $context['source_hash'], 'outcome' => 'approve',
            'reasons' => 'Syor pegawai berdasarkan pelan dan bukti.', 'conditions' => 'Sahkan kedudukan tiang di tapak.',
            'evidence_references' => 'Pelan lampu, halaman 1.', 'missing_document_explanation' => 'Dokumen susulan perlu diperoleh sebelum keputusan rasmi.',
            'recipient' => "Perunding cadangan\nAlamat perunding", 'subject' => 'Cadangan keputusan LJUP', 'letter_body' => "Teks draf pegawai.\nPerenggan kedua."];
    }

    public function test_preparation_pins_evidence_and_leaves_status_history_and_airs_unchanged(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        $before = $application->getAttributes();
        $version = ReviewVersion::query()->sole()->getAttributes();
        $history = $application->stageHistory()->count();
        Notification::fake();
        $this->actingAs($actor)->post(route('kejuruteraan.recommendations.store', $application), $this->input($actor, $application->id))->assertRedirect();
        $record = ApplicationRecommendation::query()->sole();
        $letter = ApplicationLetterDraft::query()->sole();
        $this->assertSame($before, $application->fresh()->getAttributes());
        $this->assertSame($version, ReviewVersion::query()->sole()->getAttributes());
        $this->assertSame($history, $application->stageHistory()->count());
        $this->assertSame(AcceptedSubmission::query()->sole()->id, $record->source_snapshot['accepted_submission_id']);
        $this->assertSame(ReviewVersion::query()->sole()->id, $record->source_snapshot['review_version_id']);
        $this->assertSame(AcceptedSubmission::query()->sole()->document_manifest, $record->source_snapshot['document_manifest']);
        $this->assertSame($actor->id, $record->actor_id);
        $this->assertSame(1, $record->revision);
        $this->assertSame($record->id, $letter->recommendation_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application_recommendation_drafted', 'actor_id' => $actor->id]);
        Notification::assertNothingSent();
        $this->get(route('kejuruteraan.show', $application))->assertOk()->assertSee('Sejarah syor')->assertSee($actor->name);
    }

    public function test_preview_is_escaped_and_remains_frozen_after_new_revision_and_application_edit(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        $data = $this->input($actor, $application->id);
        $data['letter_body'] = '<script>alert(1)</script>';
        $this->actingAs($actor)->post(route('kejuruteraan.recommendations.store', $application), $data)->assertRedirect();
        $first = ApplicationRecommendation::query()->sole();
        $frozen = $first->letterDraft->getAttributes();
        $data = $this->input($actor, $application->id);
        $data['letter_body'] = 'Teks baharu';
        $this->post(route('kejuruteraan.recommendations.store', $application), $data)->assertRedirect();
        $application->update(['title' => 'Tajuk diubah kemudian']);
        $this->get(route('kejuruteraan.recommendations.preview', [$application, $first]))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('DRAF — BUKAN SURAT RASMI')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Teks baharu')->assertDontSee('Tajuk diubah kemudian');
        $this->assertSame($frozen, $first->letterDraft->fresh()->getAttributes());
        $this->assertSame([1, 2], ApplicationRecommendation::query()->orderBy('revision')->pluck('revision')->all());
    }

    public function test_stale_revision_and_changed_feedback_reject_saves(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        $stale = $this->input($actor, $application->id);
        $this->actingAs($actor)->post(route('kejuruteraan.recommendations.store', $application), $stale)->assertRedirect();
        $this->post(route('kejuruteraan.recommendations.store', $application), $stale)->assertConflict();
        $fresh = $this->input($actor, $application->id);
        $finding = ReviewFinding::query()->firstOrFail();
        app(FindingFeedbackService::class)->record($actor, $finding, 'PERLU_SEMAKAN_MANUAL_PEGAWAI', 'Bukti disemak semula.');
        $this->post(route('kejuruteraan.recommendations.store', $application), $fresh)->assertConflict();
        $this->assertDatabaseCount('core.application_recommendations', 1);
    }

    public function test_public_other_department_and_access_only_staff_cannot_prepare_or_preview(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        $data = $this->input($actor, $application->id);
        $this->actingAs($actor)->post(route('kejuruteraan.recommendations.store', $application), $data)->assertRedirect();
        $record = ApplicationRecommendation::query()->sole();
        $actors = [$this->applicant('private@example.test'), User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer'])];
        $viewer = $this->engineer();
        DB::table('core.role_permissions')->where('role_id', 'engineering_officer')->where('permission_name', 'kejuruteraan.ljup.review')->delete();
        $actors[] = $viewer;
        foreach ($actors as $denied) {
            $this->actingAs($denied)->post(route('kejuruteraan.recommendations.store', $application), $data)->assertForbidden();
            $this->get(route('kejuruteraan.recommendations.preview', [$application, $record]))->assertForbidden();
        }
    }

    public function test_cross_application_and_cross_district_previews_are_not_found(): void
    {
        [$application] = $this->paidLjup();
        [$other] = $this->paidLjup();
        $actor = $this->engineer();
        $data = $this->input($actor, $application->id);
        $this->actingAs($actor)->post(route('kejuruteraan.recommendations.store', $application), $data)->assertRedirect();
        $record = ApplicationRecommendation::query()->sole();
        $this->get(route('kejuruteraan.recommendations.preview', [$other, $record]))->assertNotFound();
        $site = Site::query()->create(['development_address' => 'Tapak ujian SPU', 'district' => 'SPU', 'mukim' => 'Mukim ujian']);
        $application->sites()->attach($site->id, ['is_primary' => true]);
        $this->actingAs($this->engineer('engineering_officer', 'SPT'))
            ->get(route('kejuruteraan.recommendations.preview', [$application, $record]))->assertNotFound();
        $this->post(route('kejuruteraan.recommendations.store', $application), $data)->assertNotFound();
    }

    public function test_handover_and_matching_accepted_evidence_are_required(): void
    {
        [$application] = $this->paidLjup(null, false);
        $this->actingAs($this->engineer())->post(route('kejuruteraan.recommendations.store', $application), [])->assertConflict();
        [$handed] = $this->paidLjup();
        ReviewVersion::query()->whereHas('reviewCase', fn ($q) => $q->where('application_id', $handed->id))->update(['document_set_hash' => str_repeat('a', 64)]);
        $this->post(route('kejuruteraan.recommendations.store', $handed), [])->assertConflict();
        $this->get(route('kejuruteraan.show', $handed))->assertOk()->assertSee('Syor boleh disediakan selepas');
    }

    public function test_missing_documents_require_explanation_but_low_score_never_blocks_recommendation(): void
    {
        $draft = $this->ljupDraft($this->applicant('missing-recommendation@example.test'));
        $this->ljupUpload($draft, 'pelan_lampu');
        [$application] = $this->paidLjup($draft);
        ReviewVersion::query()->sole()->update(['score' => 0]);
        $actor = $this->engineer();
        $data = $this->input($actor, $application->id);
        $this->assertNotEmpty(AcceptedSubmission::query()->sole()->missing_required_documents);
        $data['missing_document_explanation'] = '';
        $this->actingAs($actor)->post(route('kejuruteraan.recommendations.store', $application), $data)->assertSessionHasErrors('missing_document_explanation');
        $data['missing_document_explanation'] = 'Pegawai akan mendapatkan dokumen tambahan; ini syor dalaman sahaja.';
        $this->post(route('kejuruteraan.recommendations.store', $application), $data)->assertRedirect();
        $this->assertDatabaseCount('core.application_recommendations', 1);
    }

    public function test_audit_failure_rolls_back_recommendation_and_letter(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        $data = $this->input($actor, $application->id);
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, function (AuditLog $audit): void {
            if ($audit->event === 'application_recommendation_drafted') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        try {
            app(ApplicationRecommendationService::class)->record($actor, $application->id, app(LjupRecommendationSource::class), $data);
            $this->fail('Audit failure must abort the transaction.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic audit failure', $error->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('core.application_recommendations', 0);
        $this->assertDatabaseCount('core.application_letter_drafts', 0);
    }

    public function test_database_rejects_update_delete_and_truncate_of_each_history_table(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        app(ApplicationRecommendationService::class)->record($actor, $application->id, app(LjupRecommendationSource::class), $this->input($actor, $application->id));
        foreach (['core.application_recommendations', 'core.application_letter_drafts'] as $table) {
            foreach (['update', 'delete', 'truncate'] as $operation) {
                try {
                    DB::transaction(function () use ($table, $operation): void {
                        match ($operation) {
                            'update' => DB::table($table)->update(['created_at' => now()]),
                            'delete' => DB::table($table)->delete(),
                            'truncate' => DB::statement('TRUNCATE '.$table.' CASCADE'),
                        };
                    });
                    $this->fail('History mutation should be refused.');
                } catch (QueryException $error) {
                    $this->assertSame('42501', $error->errorInfo[0]);
                }
            }
        }
    }
}
