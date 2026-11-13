<?php

declare(strict_types=1);

namespace App\Services\PaidCheck;

use App\Airs\CheckClearance;
use App\Airs\CheckService;
use App\Airs\Events\AirsCheckCompleted;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Enums\PaidCheckState;
use App\Models\ApplicantDraft;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\CheckPayment;
use App\Models\PaidCheckJourney;
use App\Services\ApplicationReferenceGenerator;
use App\Services\Precheck\DraftChecklist;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $disk = Storage::disk('local');
        foreach ($this->checklists->for($draft)['items'] as $item) {
            $upload = $item['document'];
            if ($upload === null) {
                continue;
            }
            $extension = strtolower(pathinfo($upload->original_name, PATHINFO_EXTENSION)) ?: 'pdf';
            $path = 'applications/'.$application->id.'/'.Str::ulid().'.'.$extension;
            $bytes = (string) $disk->get($upload->path);
            if (hash('sha256', $bytes) !== $upload->sha256) {
                throw new \RuntimeException('Draft document integrity check failed.');
            }
            $disk->put($path, $bytes);
            ApplicationDocument::query()->create([
                'application_id' => $application->id, 'document_type_id' => $upload->document_type_id, 'revision_number' => 1,
                'original_name' => $upload->original_name, 'storage_path' => $path, 'mime_type' => $upload->mime,
                'size_bytes' => $upload->size_bytes, 'checksum_sha256' => $upload->sha256, 'scan_status' => 'clean', 'scanned_at' => now(),
                'uploaded_by' => $draft->user_id, 'uploaded_at' => now(), 'verification_status' => DocumentVerificationStatus::Pending,
                'is_current' => true,
            ]);
        }
    }

    private function audit(PaidCheckJourney $journey, string $event, ?array $before, array $after, ?int $actorId): void
    {
        AuditLog::query()->create(['auditable_type' => PaidCheckJourney::class, 'auditable_id' => $journey->id, 'event' => $event,
            'actor_id' => $actorId, 'before_values' => $before, 'after_values' => $after, 'ip_address' => request()?->ip()]);
    }
}
