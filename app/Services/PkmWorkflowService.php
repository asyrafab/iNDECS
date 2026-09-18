<?php

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\CompletenessItemStatus;
use App\Enums\CompletenessReviewStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\DocumentRequirement;
use App\Models\PkmCompletenessItem;
use App\Models\PkmCompletenessReview;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PkmWorkflowService
{
    public function startCompletenessReview(Application $application, User $actor): Application
    {
        return DB::transaction(function () use ($application, $actor): Application {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);

            if ($locked->application_type_id !== 'PKM') {
                throw new DomainException('Tindakan ini hanya tersedia untuk permohonan PKM.');
            }

            if (
                $locked->current_stage !== ApplicationStage::Registration
                || $locked->current_status !== ApplicationStatus::Submitted
            ) {
                throw new DomainException('Permohonan ini telah meninggalkan peringkat pendaftaran.');
            }

            $previous = [
                'current_stage' => $locked->current_stage->value,
                'current_status' => $locked->current_status->value,
                'row_version' => $locked->row_version,
            ];

            $requirements = $this->applicableRequirements($locked->loadMissing('pkm'));

            if ($requirements->isEmpty()) {
                throw new DomainException('Tiada konfigurasi dokumen ditemui untuk jenis PKM ini.');
            }

            $locked->update([
                'current_stage' => ApplicationStage::CompletenessCheck,
                'current_status' => ApplicationStatus::InReview,
                'assigned_to' => $actor->id,
                'assigned_at' => now(),
                'due_date' => now()->addWeekdays(5)->toDateString(),
                'row_version' => $locked->row_version + 1,
            ]);

            $review = $locked->completenessReviews()->create([
                'cycle_number' => 1,
                'status' => CompletenessReviewStatus::InProgress,
                'opened_by' => $actor->id,
                'opened_at' => now(),
            ]);

            foreach ($requirements as $requirement) {
                $review->items()->create([
                    'document_requirement_id' => $requirement->id,
                    'document_type_id' => $requirement->document_type_id,
                    'requirement_name' => $requirement->documentType->name,
                    'is_required' => $requirement->is_required,
                    'status' => CompletenessItemStatus::Pending,
                ]);
            }

            $locked->stageHistory()->create([
                'previous_stage' => ApplicationStage::Registration,
                'new_stage' => ApplicationStage::CompletenessCheck,
                'previous_status' => ApplicationStatus::Submitted,
                'new_status' => ApplicationStatus::InReview,
                'action' => 'completeness_review_started',
                'actor_id' => $actor->id,
                'remarks' => 'Semakan kelengkapan permohonan dimulakan.',
            ]);

            AuditLog::query()->create([
                'auditable_type' => Application::class,
                'auditable_id' => $locked->id,
                'event' => 'workflow_transition',
                'actor_id' => $actor->id,
                'before_values' => $previous,
                'after_values' => [
                    'current_stage' => $locked->current_stage->value,
                    'current_status' => $locked->current_status->value,
                    'row_version' => $locked->row_version,
                ],
                'ip_address' => request()?->ip(),
            ]);

            return $locked;
        });
    }

    public function reviewItem(
        Application $application,
        PkmCompletenessItem $item,
        CompletenessItemStatus $status,
        ?string $remarks,
        User $actor,
    ): void {
        DB::transaction(function () use ($application, $item, $status, $remarks, $actor): void {
            $lockedApplication = Application::query()->lockForUpdate()->findOrFail($application->id);
            $lockedItem = PkmCompletenessItem::query()->with('review')->lockForUpdate()->findOrFail($item->id);

            $this->assertActiveReview($lockedApplication, $lockedItem->review);

            if ($status === CompletenessItemStatus::NotApplicable && $lockedItem->is_required) {
                throw new DomainException('Dokumen wajib tidak boleh ditandakan sebagai tidak berkenaan.');
            }

            $document = $lockedApplication->documents()
                ->where('document_type_id', $lockedItem->document_type_id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            if ($status === CompletenessItemStatus::Complete && $document === null) {
                throw new DomainException('Muat naik dokumen sebelum menandakan item ini lengkap.');
            }

            if ($document !== null && $status !== CompletenessItemStatus::NotApplicable) {
                $document->update([
                    'verification_status' => $status === CompletenessItemStatus::Complete
                        ? DocumentVerificationStatus::Accepted
                        : DocumentVerificationStatus::Rejected,
                    'verification_remarks' => $remarks,
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                ]);
            }

            $before = ['status' => $lockedItem->status->value, 'remarks' => $lockedItem->remarks];

            $lockedItem->update([
                'status' => $status,
                'remarks' => $remarks,
                'checked_by' => $actor->id,
                'checked_at' => now(),
            ]);

            AuditLog::query()->create([
                'auditable_type' => PkmCompletenessItem::class,
                'auditable_id' => (string) $lockedItem->id,
                'event' => 'completeness_item_reviewed',
                'actor_id' => $actor->id,
                'before_values' => $before,
                'after_values' => ['status' => $status->value, 'remarks' => $remarks],
                'ip_address' => request()?->ip(),
            ]);
        });
    }

    public function completeReview(Application $application, ?string $remarks, User $actor): Application
    {
        return DB::transaction(function () use ($application, $remarks, $actor): Application {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $review = $this->lockLatestCompletenessReview($locked);
            $this->assertActiveReview($locked, $review);

            if ($review->items->contains(
                fn (PkmCompletenessItem $item): bool => $item->is_required && $item->status !== CompletenessItemStatus::Complete,
            )) {
                throw new DomainException('Semua dokumen wajib mesti disahkan lengkap sebelum permohonan diteruskan.');
            }

            $review->update([
                'status' => CompletenessReviewStatus::Complete,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_remarks' => $remarks,
            ]);

            return $this->transition(
                $locked,
                ApplicationStage::FeeAssessment,
                ApplicationStatus::AwaitingPayment,
                'completeness_review_completed',
                'Semakan kelengkapan selesai. Permohonan diteruskan ke penilaian fi.',
                $actor,
            );
        });
    }

    public function returnForAmendment(Application $application, string $remarks, User $actor): Application
    {
        return DB::transaction(function () use ($application, $remarks, $actor): Application {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            $review = $this->lockLatestCompletenessReview($locked);
            $this->assertActiveReview($locked, $review);

            if (! $review->items->contains(
                fn (PkmCompletenessItem $item): bool => $item->is_required && $item->status === CompletenessItemStatus::Missing,
            )) {
                throw new DomainException('Tandakan sekurang-kurangnya satu dokumen wajib sebagai tidak lengkap.');
            }

            $review->update([
                'status' => CompletenessReviewStatus::Returned,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_remarks' => $remarks,
            ]);

            return $this->transition(
                $locked,
                ApplicationStage::CompletenessCheck,
                ApplicationStatus::ReturnedForAmendment,
                'returned_for_amendment',
                $remarks,
                $actor,
            );
        });
    }

    public function resubmit(Application $application, User $actor): Application
    {
        return DB::transaction(function () use ($application, $actor): Application {
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);

            if ($locked->current_stage !== ApplicationStage::CompletenessCheck
                || $locked->current_status !== ApplicationStatus::ReturnedForAmendment) {
                throw new DomainException('Permohonan ini tidak sedang menunggu pindaan.');
            }

            $previousReview = $this->lockLatestCompletenessReview($locked);
            $review = $locked->completenessReviews()->create([
                'cycle_number' => $previousReview->cycle_number + 1,
                'status' => CompletenessReviewStatus::InProgress,
                'opened_by' => $actor->id,
                'opened_at' => now(),
            ]);

            foreach ($previousReview->items as $item) {
                $review->items()->create([
                    'document_requirement_id' => $item->document_requirement_id,
                    'document_type_id' => $item->document_type_id,
                    'requirement_name' => $item->requirement_name,
                    'is_required' => $item->is_required,
                    'status' => CompletenessItemStatus::Pending,
                ]);
            }

            $locked->update([
                'current_status' => ApplicationStatus::InReview,
                'assigned_to' => $actor->id,
                'assigned_at' => now(),
                'due_date' => now()->addWeekdays(5)->toDateString(),
                'row_version' => $locked->row_version + 1,
            ]);

            $locked->stageHistory()->create([
                'previous_stage' => ApplicationStage::CompletenessCheck,
                'new_stage' => ApplicationStage::CompletenessCheck,
                'previous_status' => ApplicationStatus::ReturnedForAmendment,
                'new_status' => ApplicationStatus::InReview,
                'action' => 'amendment_resubmitted',
                'actor_id' => $actor->id,
                'remarks' => "Pindaan dihantar semula untuk semakan kelengkapan kitaran {$review->cycle_number}.",
            ]);

            AuditLog::query()->create([
                'auditable_type' => Application::class,
                'auditable_id' => $locked->id,
                'event' => 'amendment_resubmitted',
                'actor_id' => $actor->id,
                'after_values' => ['cycle_number' => $review->cycle_number, 'row_version' => $locked->row_version],
                'ip_address' => request()?->ip(),
            ]);

            return $locked;
        });
    }

    /** @return Collection<int, DocumentRequirement> */
    private function applicableRequirements(Application $application): Collection
    {
        return DocumentRequirement::query()
            ->with('documentType')
            ->where('application_type_id', 'PKM')
            ->where('is_active', true)
            ->whereHas('documentType', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) use ($application): void {
                $query->whereNull('permission_category')
                    ->orWhere('permission_category', $application->pkm->permission_category);
            })
            ->where(function ($query) use ($application): void {
                $query->whereNull('permission_type')
                    ->orWhere('permission_type', $application->pkm->permission_type);
            })
            ->orderByRaw('CASE WHEN permission_category IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN permission_type IS NULL THEN 0 ELSE 1 END DESC')
            ->orderBy('sort_order')
            ->get()
            ->unique('document_type_id')
            ->sortBy('sort_order')
            ->values();
    }

    private function assertActiveReview(Application $application, PkmCompletenessReview $review): void
    {
        if ($review->application_id !== $application->id
            || $application->current_stage !== ApplicationStage::CompletenessCheck
            || $application->current_status !== ApplicationStatus::InReview
            || $review->status !== CompletenessReviewStatus::InProgress) {
            throw new DomainException('Senarai semak ini bukan kitaran semakan yang aktif.');
        }
    }

    private function transition(
        Application $application,
        ApplicationStage $stage,
        ApplicationStatus $status,
        string $action,
        string $remarks,
        User $actor,
    ): Application {
        $before = [
            'current_stage' => $application->current_stage->value,
            'current_status' => $application->current_status->value,
            'row_version' => $application->row_version,
        ];

        $previousStage = $application->current_stage;
        $previousStatus = $application->current_status;

        $application->update([
            'current_stage' => $stage,
            'current_status' => $status,
            'row_version' => $application->row_version + 1,
        ]);

        $application->stageHistory()->create([
            'previous_stage' => $previousStage,
            'new_stage' => $stage,
            'previous_status' => $previousStatus,
            'new_status' => $status,
            'action' => $action,
            'actor_id' => $actor->id,
            'remarks' => $remarks,
        ]);

        AuditLog::query()->create([
            'auditable_type' => Application::class,
            'auditable_id' => $application->id,
            'event' => 'workflow_transition',
            'actor_id' => $actor->id,
            'before_values' => $before,
            'after_values' => [
                'current_stage' => $application->current_stage->value,
                'current_status' => $application->current_status->value,
                'row_version' => $application->row_version,
            ],
            'ip_address' => request()?->ip(),
        ]);

        return $application;
    }

    /**
     * Resolve and row-lock the application's latest completeness review, with items eager loaded.
     *
     * `latestCompletenessReview()` is a `HasOne::ofMany('cycle_number', 'max')` relation, which
     * compiles to a query with a GROUP BY / aggregate subquery. PostgreSQL rejects `FOR UPDATE`
     * combined with GROUP BY ("Feature not supported"); SQLite silently ignores `lockForUpdate()`
     * entirely, which is why this went unnoticed until migrating off SQLite. The caller already
     * holds a row lock on the parent `Application`, so resolving the review's ID via the ofMany
     * relation first (unlocked) is safe: no other transaction can start a new review for this
     * application without first locking the same Application row.
     */
    private function lockLatestCompletenessReview(Application $application): PkmCompletenessReview
    {
        $reviewId = $application->latestCompletenessReview()->value('id');

        return PkmCompletenessReview::query()
            ->with('items')
            ->lockForUpdate()
            ->findOrFail($reviewId);
    }
}
