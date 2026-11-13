<?php

declare(strict_types=1);

namespace App\Services\PaidCheck;

use App\Airs\CheckClearance;
use App\Airs\CheckService;
use App\Airs\Events\AirsCheckCompleted;
use App\Airs\Models\ReviewVersion;
use App\Airs\Models\SystemConfigSnapshot;
use App\Models\AcceptedSubmission;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\PaidCheckState;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\CheckPayment;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\ApplicationReferenceGenerator;
use App\Services\Precheck\DraftChecklist;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The applicant's paid AIRS check journey (design §5.1): payment creates the formal application
 * and starts check 1; one optional amendment runs check 2; the application is then handed over.
 */
final class PaidCheckJourneyService
{
    public const AMENDMENT_WORKING_DAYS = 5;

    public function __construct(
        private readonly DraftChecklist $checklists,
        private readonly ApplicationReferenceGenerator $references,
        private readonly WorkingCalendar $calendar,
        private readonly CheckService $checks,
    ) {}

    /** Idempotent: a payment's draft gets exactly one application, journey and check 1. */
    public function paid(CheckPayment $payment): PaidCheckJourney
    {
        $created = false;
        $journey = DB::transaction(function () use ($payment, &$created): PaidCheckJourney {
            $draft = ApplicantDraft::query()->lockForUpdate()->findOrFail($payment->draft_id);
            $existing = PaidCheckJourney::query()->where('draft_id', $draft->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            abort_unless(CheckPayment::query()->whereKey($payment->id)->where('status', 'paid')->exists(), 409, 'Payment is not confirmed.');
            $type = $draft->applicationType()->firstOrFail();
            $application = Application::query()->create([
                'application_type_id' => $type->id,
                'application_reference' => $this->references->next($type->id, now()),
                'title' => $draft->title,
                'description' => $draft->site_address,
                'submission_date' => today(),
                'submission_year' => (int) now()->format('Y'),
                'responsible_department_id' => $type->primary_department_id,
                'current_stage' => ApplicationStage::TechnicalCheck,
                'current_status' => ApplicationStatus::InReview,
            ]);
            $this->copyDocuments($draft, $application);
            $journey = new PaidCheckJourney;
            $journey->forceFill(['draft_id' => $draft->id, 'application_id' => $application->id, 'payment_id' => $payment->id,
                'state' => PaidCheckState::Check1Running, 'lighting_types' => $draft->lightingTypes()])->save();
            $this->audit($journey, 'paid_check_started', null, ['application_id' => $application->id, 'payment_id' => $payment->id], $draft->user_id);
            $created = true;

            return $journey;
        });
        if ($created) {
            $this->startCheck($journey);
        }

        return $journey->fresh();
    }

    /** The single amendment window is open: awaiting the applicant, not yet amended, deadline not passed. */
    public static function amendmentOpen(PaidCheckJourney $journey): bool
    {
        return $journey->state === PaidCheckState::AwaitingApplicant && $journey->amended_at === null
            && $journey->amendment_due_at !== null && CarbonImmutable::now()->lessThanOrEqualTo($journey->amendment_due_at);
    }

    /** A draft can be edited before payment, and afterwards only while its single amendment window is open. */
    public static function draftEditable(ApplicantDraft $draft): bool
    {
        $journey = PaidCheckJourney::query()->where('draft_id', $draft->id)->first();

        return $journey === null || self::amendmentOpen($journey);
    }

    /** One extension of 5 working days, before the deadline. */
    public function extend(User $owner, PaidCheckJourney $journey): void
    {
        DB::transaction(function () use ($owner, $journey): void {
            $locked = $this->lockedFor($owner, $journey);
            if (! self::amendmentOpen($locked)) {
                throw ValidationException::withMessages(['check' => 'Tempoh pindaan telah tamat.']);
            }
            if ($locked->extended_at !== null) {
                throw ValidationException::withMessages(['check' => 'Tempoh pindaan hanya boleh dilanjutkan sekali sebelum tamat.']);
            }
            $before = $locked->amendment_due_at;
            $due = $this->calendar->addWorkingDays($before, self::AMENDMENT_WORKING_DAYS);
            $locked->forceFill(['amendment_due_at' => $due, 'extended_at' => now()])->save();
            $this->audit($locked, 'amendment_window_extended', ['amendment_due_at' => $before->toIso8601String()], ['amendment_due_at' => $due->toIso8601String()], $owner->id);
        });
    }

    /** The single amendment: changed uploads become new document revisions, then check 2 runs. */
    public function submitAmendment(User $owner, PaidCheckJourney $journey): void
    {
        $journey = DB::transaction(function () use ($owner, $journey): PaidCheckJourney {
            $locked = $this->lockedFor($owner, $journey);
            if (! self::amendmentOpen($locked)) {
                throw ValidationException::withMessages(['check' => $locked->amended_at !== null || $locked->state !== PaidCheckState::AwaitingApplicant
                    ? 'Pindaan hanya boleh dibuat sekali.' : 'Tempoh pindaan telah tamat.']);
            }
            $draft = ApplicantDraft::query()->lockForUpdate()->findOrFail($locked->draft_id);
            $changes = $this->reviseDocuments($draft, $locked->application);
            $locked->forceFill(['amended_at' => now(), 'state' => PaidCheckState::Check2Running, 'lighting_types' => $draft->lightingTypes()])->save();
            $this->audit($locked, 'amendment_submitted', null, $changes + ['lighting_types' => $draft->lightingTypes()], $owner->id);

            return $locked;
        });
        $this->startCheck($journey);
    }

    /** The applicant accepts check 1 as final; no check 2 runs. */
    public function submitAsIs(User $owner, PaidCheckJourney $journey): void
    {
        DB::transaction(function () use ($owner, $journey): void {
            $locked = $this->lockedFor($owner, $journey);
            if ($locked->state !== PaidCheckState::AwaitingApplicant || $locked->amended_at !== null) {
                throw ValidationException::withMessages(['check' => 'Permohonan ini tidak lagi menunggu tindakan anda.']);
            }
        });
        $this->handOver($journey, 1);
    }

    /**
     * Hands the application to the responsible department with the final check (idempotent). Writes the
     * immutable accepted-submission record; missing required documents are flagged, never rejected.
     */
    public function handOver(PaidCheckJourney $journey, int $finalCheckNumber): void
    {
        DB::transaction(function () use ($journey, $finalCheckNumber): void {
            $locked = PaidCheckJourney::query()->lockForUpdate()->findOrFail($journey->id);
            if ($locked->state === PaidCheckState::HandedOver) {
                return;
            }
            $version = ReviewVersion::query()->where('check_number', $finalCheckNumber)
                ->whereHas('reviewCase', fn ($query) => $query->where('application_id', $locked->application_id))->firstOrFail();
            $manifest = (array) (SystemConfigSnapshot::query()->where('review_version_id', $version->id)->value('config_json')['document_manifest'] ?? []);
            $missing = app(ApplicantCheckPresenter::class)->missingRequired($locked, $finalCheckNumber);
            $payment = $locked->payment;
            AcceptedSubmission::query()->create([
                'application_id' => $locked->application_id, 'document_manifest' => $manifest, 'document_set_hash' => $version->document_set_hash,
                'clearance_kind' => 'paid', 'payment_clearance_reference' => $payment->reference, 'payment_cleared_at' => $payment->paid_at,
                'accepted_at' => now(), 'missing_required_documents' => $missing,
            ]);
            $locked->application->update(['current_stage' => ApplicationStage::TechnicalCirculation, 'current_status' => ApplicationStatus::Submitted]);
            $locked->forceFill(['state' => PaidCheckState::HandedOver, 'final_check_number' => $finalCheckNumber, 'handed_over_at' => now()])->save();
            $this->audit($locked, 'paid_check_handed_over', null, ['final_check_number' => $finalCheckNumber, 'missing_required_documents' => $missing], null);
        });
    }

    /** Hands over every journey whose amendment window ended without an amendment (check 1 final). */
    public function handOverExpired(): int
    {
        $expired = PaidCheckJourney::query()->where('state', PaidCheckState::AwaitingApplicant)->whereNull('amended_at')
            ->where('amendment_due_at', '<', now())->orderBy('amendment_due_at')->limit(100)->get();
        foreach ($expired as $journey) {
            $this->handOver($journey, 1);
        }

        return $expired->count();
    }

    /** Listener for AirsCheckCompleted: opens the amendment window after check 1, hands over after check 2. */
    public function checkCompleted(AirsCheckCompleted $event): void
    {
        DB::transaction(function () use ($event): void {
            $journey = PaidCheckJourney::query()->where('application_id', $event->applicationId)->lockForUpdate()->first();
            if ($journey === null) {
                return;
            }
            if ($event->checkNumber === 1 && $journey->state === PaidCheckState::Check1Running) {
                $due = $this->calendar->addWorkingDays(CarbonImmutable::now(), self::AMENDMENT_WORKING_DAYS);
                $journey->forceFill(['state' => PaidCheckState::AwaitingApplicant, 'amendment_due_at' => $due])->save();
                $this->audit($journey, 'paid_check_result_ready', null, ['check_number' => 1, 'amendment_due_at' => $due->toIso8601String()], null);
            }
        });
        if ($event->checkNumber === 2) {
            $journey = PaidCheckJourney::query()->where('application_id', $event->applicationId)->first();
            if ($journey?->state === PaidCheckState::Check2Running) {
                $this->handOver($journey, 2);
            }
        }
    }

    private function lockedFor(User $owner, PaidCheckJourney $journey): PaidCheckJourney
    {
        $locked = PaidCheckJourney::query()->lockForUpdate()->findOrFail($journey->id);
        abort_unless(ApplicantDraft::query()->whereKey($locked->draft_id)->where('user_id', $owner->id)->exists(), 404);

        return $locked;
    }

    /**
     * Brings the application's current documents in line with the draft: changed uploads become new
     * revisions, new items are added, removed or no-longer-applicable items stop being current.
     *
     * @return array{revised: list<string>, added: list<string>, withdrawn: list<string>}
     */
    private function reviseDocuments(ApplicantDraft $draft, Application $application): array
    {
        $changes = ['revised' => [], 'added' => [], 'withdrawn' => []];
        $current = $application->currentDocuments()->get()->keyBy('document_type_id');
        $kept = [];
        foreach ($this->checklists->for($draft)['items'] as $item) {
            $upload = $item['document'];
            if ($upload === null) {
                continue;
            }
            $existing = $current->get($upload->document_type_id);
            $kept[] = $upload->document_type_id;
            if ($existing !== null && $existing->checksum_sha256 === $upload->sha256) {
                continue;
            }
            $copy = $this->copyUpload($draft, $application, $upload, $existing);
            $changes[$existing === null ? 'added' : 'revised'][] = $copy->original_name;
        }
        foreach ($current as $typeId => $document) {
            if (! in_array($typeId, $kept, true)) {
                $document->update(['is_current' => false]);
                $changes['withdrawn'][] = $document->original_name;
            }
        }

        return $changes;
    }

    private function startCheck(PaidCheckJourney $journey): void
    {
        $payment = $journey->payment;
        $application = $journey->application;
        try {
            $this->checks->start($application, new CheckClearance('paid', $payment->reference, $payment->paid_at),
                $journey->lighting_types, $application->currentDocuments()->get());
        } catch (Throwable $exception) {
            // The payment and application stand; staff rerun the check (it never consumes a paid check).
            report($exception);
            $this->audit($journey, 'paid_check_start_failed', null, ['error' => class_basename($exception), 'message' => mb_substr($exception->getMessage(), 0, 200)], null);
        }
    }

    /** Copies the latest upload of every checklist item that currently applies into the application's documents. */
    private function copyDocuments(ApplicantDraft $draft, Application $application): void
    {
        foreach ($this->checklists->for($draft)['items'] as $item) {
            if ($item['document'] !== null) {
                $this->copyUpload($draft, $application, $item['document'], null);
            }
        }
    }

    /** Copies one verified draft upload into the application, superseding $previous when given. */
    private function copyUpload(ApplicantDraft $draft, Application $application, ApplicantDraftDocument $upload, ?ApplicationDocument $previous): ApplicationDocument
    {
        $disk = Storage::disk('local');
        $extension = strtolower(pathinfo($upload->original_name, PATHINFO_EXTENSION)) ?: 'pdf';
        $path = 'applications/'.$application->id.'/'.Str::ulid().'.'.$extension;
        $bytes = (string) $disk->get($upload->path);
        if (hash('sha256', $bytes) !== $upload->sha256) {
            throw new \RuntimeException('Draft document integrity check failed.');
        }
        $disk->put($path, $bytes);
        $previous?->update(['is_current' => false]);
        $revision = 1 + (int) ApplicationDocument::query()->where('application_id', $application->id)
            ->where('document_type_id', $upload->document_type_id)->max('revision_number');

        return ApplicationDocument::query()->create([
            'application_id' => $application->id, 'document_type_id' => $upload->document_type_id, 'revision_number' => $revision,
            'original_name' => $upload->original_name, 'storage_path' => $path, 'mime_type' => $upload->mime,
            'size_bytes' => $upload->size_bytes, 'checksum_sha256' => $upload->sha256, 'scan_status' => 'clean', 'scanned_at' => now(),
            'uploaded_by' => $draft->user_id, 'uploaded_at' => now(), 'verification_status' => DocumentVerificationStatus::Pending,
            'is_current' => true, 'supersedes_id' => $previous?->id,
        ]);
    }

    private function audit(PaidCheckJourney $journey, string $event, ?array $before, array $after, ?int $actorId): void
    {
        AuditLog::query()->create(['auditable_type' => PaidCheckJourney::class, 'auditable_id' => $journey->id, 'event' => $event,
            'actor_id' => $actorId, 'before_values' => $before, 'after_values' => $after, 'ip_address' => request()?->ip()]);
    }
}
