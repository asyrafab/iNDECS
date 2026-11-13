<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Jobs\SyncAirsReview;
use App\Models\AcceptedSubmission;
use App\Models\AirsFeedback;
use App\Models\AirsReview;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Airs\AirsReviews;
use App\Services\Airs\DocumentManifest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

final class AirsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Application $application;

    private ApplicationDocument $document;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Queue::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['airs.enabled' => true, 'airs.url' => 'https://airs.internal', 'airs.token' => str_repeat('x', 64),
            'airs.modules.PBK' => 'STREET_LIGHTING']); // Transport fixture only, not a production rule mapping.
        $this->officer = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        $this->application = Application::create(['application_type_id' => 'PBK', 'application_reference' => 'PBK/2026/00999',
            'title' => 'Integration fixture', 'submission_date' => today(), 'submission_year' => 2026,
            'responsible_department_id' => 'JBANG', 'current_stage' => ApplicationStage::DocumentVerification, 'current_status' => ApplicationStatus::InReview]);
        $type = DocumentType::firstOrCreate(['code' => 'AIRS_TEST'], ['name' => 'Test plan', 'allowed_extensions' => 'pdf', 'max_file_size_kb' => 20480, 'is_active' => true]);
        $bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        Storage::disk('local')->put('fixture/plan.pdf', $bytes);
        $this->document = ApplicationDocument::create(['application_id' => $this->application->id, 'document_type_id' => $type->id,
            'original_name' => 'plan.pdf', 'storage_path' => 'fixture/plan.pdf', 'mime_type' => 'application/pdf',
            'revision_number' => 1, 'size_bytes' => strlen($bytes), 'checksum_sha256' => hash('sha256', $bytes),
            'scan_status' => 'clean', 'scanned_at' => now(), 'uploaded_by' => $this->officer->id, 'uploaded_at' => now(), 'is_current' => true]);
        $this->actingAs($this->officer);
    }

    private function accepted(): AcceptedSubmission
    {
        $manifest = app(DocumentManifest::class)->forApplication($this->application);

        return AcceptedSubmission::create(['application_id' => $this->application->id, 'document_manifest' => $manifest,
            'document_set_hash' => DocumentManifest::hash($manifest), 'clearance_kind' => 'paid',
            'payment_clearance_reference' => 'TEST-'.Str::ulid(), 'payment_cleared_at' => now()->subMinute(), 'accepted_at' => now()]);
    }

    private function review(): AirsReview
    {
        $this->accepted();

        return app(AirsReviews::class)->queue($this->application, $this->officer, ['BESI']);
    }

    private function remoteResult(AirsReview $review, array $findings = []): array
    {
        return ['request_id' => $review->id, 'review_id' => (string) Str::ulid(), 'application_id' => $this->application->id,
            'submission_id' => $review->submission_id, 'document_set_hash' => $review->submission->document_set_hash,
            'module_code' => 'STREET_LIGHTING', 'status' => 'COMPLETED', 'progress' => 100, 'findings' => $findings];
    }

    public function test_staff_registration_without_payment_clearance_cannot_start_review(): void
    {
        $this->get(route('airs.application', $this->application))->assertOk()->assertSee('Serahan rasmi dengan pengesahan bayaran belum direkodkan');
        $this->post(route('airs.store', $this->application), ['lighting_types' => ['BESI']])->assertSessionHasErrors('airs');
        $this->assertDatabaseCount('integration.airs_reviews', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_paid_version_is_queued_once_and_document_changes_require_new_acceptance(): void
    {
        $review = $this->review();
        $again = app(AirsReviews::class)->queue($this->application, $this->officer, ['BESI']);
        $this->assertSame($review->id, $again->id);
        Queue::assertPushed(SyncAirsReview::class, 1);
        $this->document->update(['original_name' => 'changed.pdf']);
        $this->post(route('airs.store', $this->application), ['lighting_types' => ['BESI']])->assertSessionHasErrors('airs');
        $this->assertFalse(app(AirsReviews::class)->isCurrent($review));
    }

    public function test_sync_uses_service_auth_and_never_changes_application_state(): void
    {
        $review = $this->review();
        $before = $this->application->fresh()->getAttributes();
        Http::fake(['https://airs.internal/api/v1/reviews' => Http::response(['data' => $this->remoteResult($review)], 202)]);
        app(AirsReviews::class)->sync($review);
        $this->assertSame('completed', $review->fresh()->state);
        $this->assertSame($before, $this->application->fresh()->getAttributes());
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.str_repeat('x', 64)));
        $this->get(route('airs.show', $review))->assertOk()->assertDontSee(str_repeat('x', 64));
    }

    public function test_wrong_correlation_and_unavailable_service_do_not_replace_results(): void
    {
        $review = $this->review();
        $bad = $this->remoteResult($review);
        $bad['submission_id'] = (string) Str::ulid();
        foreach ([Http::response(['data' => $bad]), Http::response([], 503)] as $response) {
            Http::fake(['*' => $response]);
            try {
                app(AirsReviews::class)->sync($review);
                $this->fail('Invalid result accepted.');
            } catch (\Throwable $error) {
                $this->assertNotInstanceOf(AssertionFailedError::class, $error);
            }
            $this->assertNull($review->fresh()->remote_id);
            $this->assertNull($review->fresh()->result);
            $this->assertNotNull($review->fresh()->last_error);
        }
    }

    public function test_feedback_is_attributed_idempotent_and_original_finding_is_preserved(): void
    {
        $review = $this->review();
        $finding = ['id' => (string) Str::ulid(), 'rule_id' => 'TEST-1', 'title' => 'Fixture', 'status' => 'BELUM_DITUNAIKAN',
            'confidence' => 0.8, 'finding' => 'Original AI text', 'evidence' => [['document_id' => $this->document->id, 'page' => 1]]];
        $review->update(['state' => 'completed', 'result' => $this->remoteResult($review, [$finding])]);
        $data = ['event_id' => (string) Str::ulid(), 'finding_id' => $finding['id'], 'status' => 'DITUNAIKAN', 'comment' => 'Verified against evidence'];
        $this->post(route('airs.feedback', $review), $data)->assertRedirect();
        $this->post(route('airs.feedback', $review), $data)->assertRedirect();
        $this->assertDatabaseCount('integration.airs_feedback', 1);
        $this->assertSame($this->officer->id, AirsFeedback::first()->actor_id);
        $this->assertSame('BELUM_DITUNAIKAN', $review->fresh()->result['findings'][0]['status']);
        $this->document->update(['original_name' => 'new-round.pdf']);
        $this->post(route('airs.feedback', $review), $data)->assertStatus(409);
    }

    public function test_other_users_cannot_read_review_or_evidence(): void
    {
        $review = $this->review();
        $outsider = User::factory()->create(['department_id' => 'JPBD', 'role' => 'planning_officer']);
        $this->actingAs($outsider)->get(route('airs.show', $review))->assertForbidden();
        $this->get(route('airs.document', [$review, $this->document]))->assertForbidden();
        $this->post(route('airs.refresh', $review))->assertForbidden();
    }

    public function test_evidence_download_is_bound_to_original_manifest_and_integrity(): void
    {
        $review = $this->review();
        $this->get(route('airs.document', [$review, $this->document]))->assertOk()->assertDownload('plan.pdf');
        Storage::disk('local')->put($this->document->storage_path, 'tampered');
        $this->get(route('airs.document', [$review, $this->document]))->assertStatus(409);
    }

    public function test_acceptance_ledger_is_immutable_at_database_level(): void
    {
        $accepted = $this->accepted();
        $this->expectException(QueryException::class);
        DB::table('core.accepted_submissions')->where('id', $accepted->id)->update(['clearance_kind' => 'waived']);
    }

    public function test_unsupported_module_blocks_without_endless_retry_and_stale_queued_request_is_not_sent(): void
    {
        $review = $this->review();
        Http::fake(['*' => Http::response([], 422)]);
        app(AirsReviews::class)->sync($review);
        $this->assertSame('blocked', $review->fresh()->state);
        $this->document->update(['original_name' => 'new.pdf']);
        Http::fake();
        app(AirsReviews::class)->sync($review->fresh());
        $this->assertSame('superseded', $review->fresh()->state);
        Http::assertNothingSent();
    }

    public function test_positive_finding_without_source_evidence_is_never_accepted(): void
    {
        $review = $this->review();
        $finding = ['id' => (string) Str::ulid(), 'rule_id' => 'TEST-1', 'title' => 'Fixture', 'status' => 'DITUNAIKAN', 'confidence' => 1, 'evidence' => []];
        Http::fake(['*' => Http::response(['data' => $this->remoteResult($review, [$finding])])]);
        try {
            app(AirsReviews::class)->sync($review);
            $this->fail('Unsupported compliance accepted.');
        } catch (\RuntimeException) {
            $this->assertNull($review->fresh()->result);
        }
    }
}
