<?php

declare(strict_types=1);

namespace App\Services\Airs;

use App\Jobs\SyncAirsReview;
use App\Models\AcceptedSubmission;
use App\Models\AirsFeedback;
use App\Models\AirsReview;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AirsReviews
{
    public const STATUSES = ['DITUNAIKAN', 'BELUM_DITUNAIKAN', 'TIDAK_BERKAITAN', 'TIDAK_DAPAT_DIKESAN', 'PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI'];

    public function __construct(private readonly DocumentManifest $documents, private readonly AirsClient $client) {}

    public function assertReviewer(Application $application, User $actor): void
    {
        abort_unless($this->canReview($application, $actor), 403);
    }

    public function canReview(Application $application, User $actor): bool
    {
        return $actor->canAccessApplication($application) && ($application->responsible_department_id === 'JBANG'
            ? $actor->canPerformBangunan('review', $application->application_type_id)
            : ($application->application_type_id === 'PKM' && $actor->canPerformPkm('perancang.pkm.review')));
    }

    public function latestSubmission(Application $application): ?AcceptedSubmission
    {
        return AcceptedSubmission::query()->where('application_id', $application->id)->orderByDesc('accepted_at')->orderByDesc('id')->first();
    }

    public function isCurrent(AirsReview $review): bool
    {
        try {
            return $this->latestSubmission($review->application)?->id === $review->submission_id
                && DocumentManifest::hash($this->documents->forApplication($review->application)) === $review->submission->document_set_hash;
        } catch (ValidationException) {
            return false;
        }
    }

    public function queue(Application $application, User $actor, array $lightingTypes = []): AirsReview
    {
        $this->assertReviewer($application, $actor);
        abort_unless(config('airs.enabled'), 503, 'Sambungan AIRS belum diaktifkan.');

        return DB::transaction(function () use ($application, $actor, $lightingTypes): AirsReview {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);
            $submission = $this->latestSubmission($application);
            if (! $submission || $submission->accepted_at->isFuture() || $submission->payment_cleared_at->isFuture()
                || ! in_array($submission->clearance_kind, ['paid', 'waived'], true)
                || DocumentManifest::hash($this->documents->forApplication($application, true)) !== $submission->document_set_hash
                || DocumentManifest::hash($submission->document_manifest) !== $submission->document_set_hash) {
                throw ValidationException::withMessages(['airs' => 'Serahan rasmi dengan pengesahan bayaran dan versi dokumen sepadan diperlukan.']);
            }
            $module = config('airs.modules.'.$application->application_type_id);
            if (! is_string($module)) {
                throw ValidationException::withMessages(['airs' => 'Modul AIRS belum dikonfigurasi untuk jenis permohonan ini.']);
            }
            $lightingTypes = array_values(array_unique($lightingTypes));
            if (array_diff($lightingTypes, ['BESI', 'KONKRIT', 'SOLAR']) || ($module === 'STREET_LIGHTING' && $lightingTypes === [])) {
                throw ValidationException::withMessages(['airs' => 'Pilih jenis lampu yang sah untuk semakan LJUP.']);
            }
            $review = AirsReview::query()->firstOrCreate(['submission_id' => $submission->id, 'module_code' => $module], [
                'application_id' => $application->id, 'requested_by' => $actor->id, 'lighting_types' => $lightingTypes, 'state' => 'queued',
            ]);
            if ($review->lighting_types !== $lightingTypes) {
                throw ValidationException::withMessages(['airs' => 'Pilihan semakan bagi serahan ini telah dikunci.']);
            }
            if ($review->wasRecentlyCreated) {
                $this->audit($application, $actor, 'airs_review_requested', ['review_id' => $review->id, 'submission_id' => $submission->id]);
                SyncAirsReview::dispatch($review->id)->onQueue(config('airs.queue'))->afterCommit();
            }

            return $review;
        });
    }

    public function sync(AirsReview $review): void
    {
        if (! $review->remote_id && ! $this->isCurrent($review)) {
            $review->update(['state' => 'superseded', 'last_error' => 'Versi dokumen berubah sebelum penghantaran. Serahan baharu diperlukan.']);

            return;
        }
        try {
            if (! $review->remote_id) {
                $this->documents->forApplication($review->application, true);
            }
            $data = $review->remote_id ? $this->client->status($review) : $this->client->submit($review);
            $this->validateResult($review, $data);
            $review->update(['remote_id' => $data['review_id'], 'state' => match ($data['status']) {
                'COMPLETED' => 'completed', 'FAILED' => 'failed', default => 'processing',
            }, 'result' => $data, 'last_error' => null]);
            foreach (AirsFeedback::query()->where('review_id', $review->id)->whereNull('sent_at')->get() as $feedback) {
                $this->client->feedback($review, ['event_id' => $feedback->id, 'finding_id' => $feedback->finding_id,
                    'actor_id' => (string) $feedback->actor_id, 'status' => $feedback->status, 'comment' => $feedback->comment]);
                $feedback->update(['sent_at' => now()]);
            }
        } catch (\Throwable $error) {
            if ($error instanceof RequestException && in_array($error->response->status(), [409, 413, 422], true)) {
                $review->update(['state' => 'blocked', 'last_error' => 'Modul atau pakej dokumen tidak diterima oleh AIRS. Semak konfigurasi sebelum mencuba semula.']);

                return;
            }
            $review->update(['last_error' => 'Sambungan atau pengesahan hasil AIRS gagal. Permintaan asal disimpan untuk cubaan semula.']);
            throw new RuntimeException('AIRS synchronisation failed; the original request is retained.');
        }
    }

    private function validateResult(AirsReview $review, array $data): void
    {
        foreach (['request_id' => $review->id, 'application_id' => $review->application_id, 'submission_id' => $review->submission_id,
            'document_set_hash' => $review->submission->document_set_hash, 'module_code' => $review->module_code] as $key => $expected) {
            if (($data[$key] ?? null) !== $expected) {
                throw new RuntimeException('AIRS correlation mismatch.');
            }
        }
        if (! Str::isUlid($data['review_id'] ?? '') || ($review->remote_id && $review->remote_id !== $data['review_id'])
            || ! in_array($data['status'] ?? null, ['UPLOADED', 'EXTRACTING', 'INVENTORY', 'EXTRACTING_TEXT', 'RENDERING', 'CLASSIFYING', 'EXTRACTING_PROFILE', 'EVALUATING_RULES', 'CONSOLIDATING', 'GENERATING_REPORT', 'COMPLETED', 'FAILED'], true)
            || ! is_int($data['progress'] ?? null) || $data['progress'] < 0 || $data['progress'] > 100
            || ! is_array($data['findings'] ?? null) || count($data['findings']) > 1000) {
            throw new RuntimeException('Invalid AIRS response.');
        }
        $ids = array_column($review->submission->document_manifest, 'id');
        foreach ($data['findings'] as $finding) {
            if (! is_array($finding) || ! Str::isUlid($finding['id'] ?? '')
                || ! in_array($finding['status'] ?? null, self::STATUSES, true)
                || ! is_string($finding['rule_id'] ?? null) || ! is_string($finding['title'] ?? null)
                || ! is_numeric($finding['confidence'] ?? null) || $finding['confidence'] < 0 || $finding['confidence'] > 1
                || ! is_array($finding['evidence'] ?? null)) {
                throw new RuntimeException('Invalid finding.');
            }
            foreach ($finding['evidence'] as $evidence) {
                if (! in_array($evidence['document_id'] ?? null, $ids, true) || ! is_int($evidence['page'] ?? null) || $evidence['page'] < 1) {
                    throw new RuntimeException('Invalid evidence reference.');
                }
            }
            if ($finding['status'] === 'DITUNAIKAN' && $finding['evidence'] === []) {
                throw new RuntimeException('Unsupported positive finding.');
            }
        }
    }

    public function feedback(AirsReview $review, User $actor, array $data): void
    {
        $this->assertReviewer($review->application, $actor);
        abort_unless($this->isCurrent($review) && $review->state === 'completed', 409);
        $finding = collect($review->result['findings'])->firstWhere('id', $data['finding_id']);
        abort_unless($finding && ($data['status'] !== 'DITUNAIKAN' || $finding['evidence'] !== []), 422);
        DB::transaction(function () use ($review, $actor, $data): void {
            Application::query()->lockForUpdate()->findOrFail($review->application_id);
            abort_unless($this->isCurrent($review), 409);
            $feedback = AirsFeedback::query()->firstOrCreate(['id' => $data['event_id']], [
                'review_id' => $review->id, 'finding_id' => $data['finding_id'], 'actor_id' => $actor->id,
                'status' => $data['status'], 'comment' => $data['comment'] ?? null,
            ]);
            abort_unless($feedback->review_id === $review->id && $feedback->finding_id === $data['finding_id']
                && $feedback->actor_id === $actor->id && $feedback->status === $data['status'] && $feedback->comment === ($data['comment'] ?? null), 409);
            if ($feedback->wasRecentlyCreated) {
                $this->audit($review->application, $actor, 'airs_finding_feedback', ['feedback_id' => $feedback->id, 'status' => $feedback->status]);
            }
            SyncAirsReview::dispatch($review->id)->onQueue(config('airs.queue'))->afterCommit();
        });
    }

    private function audit(Application $application, User $actor, string $event, array $data): void
    {
        AuditLog::query()->create(['auditable_type' => Application::class, 'auditable_id' => $application->id,
            'actor_id' => $actor->id, 'event' => $event, 'after_values' => $data, 'ip_address' => request()?->ip()]);
    }
}
