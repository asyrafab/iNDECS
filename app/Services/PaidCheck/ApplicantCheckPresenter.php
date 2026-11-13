<?php

declare(strict_types=1);

namespace App\Services\PaidCheck;

use App\Airs\Models\GeneratedReport;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewVersion;
use App\Airs\Models\SystemConfigSnapshot;
use App\Enums\PaidCheckState;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\PaidCheckJourney;
use App\Services\Checklist\ChecklistRuleEvaluator;
use App\Services\DocumentCatalogueService;
use Carbon\CarbonImmutable;

/**
 * The applicant-safe view of AIRS checks (design §5.6). Only rule title, status label, standard
 * comment, evidence document/page and score leave this class. Never AI rationale, confidence,
 * extracted values, manual-review reasons, warnings, shadow outcome or officer comments.
 */
final class ApplicantCheckPresenter
{
    public const STATUS_LABELS = [
        'DITUNAIKAN' => 'Dipenuhi',
        'BELUM_DITUNAIKAN' => 'Belum dipenuhi',
        'TIDAK_BERKAITAN' => 'Tidak berkaitan',
        'TIDAK_DAPAT_DIKESAN' => 'Tidak dikesan',
        'PERLU_SEMAKAN_MANUAL' => 'Akan disemak oleh pegawai',
        'PERLU_SEMAKAN_MANUAL_PEGAWAI' => 'Akan disemak oleh pegawai',
    ];

    public function __construct(private readonly ChecklistRuleEvaluator $rules) {}

    /**
     * @return array{state: PaidCheckState, checks: list<array{number: int, status: string, message: ?string, score: ?int, items: list<array>, missing_required: list<string>}>, amendment_due_at: ?CarbonImmutable, can_extend: bool, can_amend: bool, can_submit: bool}
     */
    public function for(PaidCheckJourney $journey): array
    {
        $case = ReviewCase::query()->where('application_id', $journey->application_id)->first();
        $versions = $case === null ? collect() : $case->versions()->orderBy('check_number')->get();
        $checks = $versions->map(fn (ReviewVersion $version) => $this->check($journey, $case, $version))->values()->all();
        // A running journey whose check never started (recorded as paid_check_start_failed) needs staff, not polling.
        $waitingFor = PaidCheckJourneyService::runningCheckNumber($journey);
        if ($waitingFor !== null && ! $versions->contains('check_number', $waitingFor)
            && \App\Models\AuditLog::query()->where('auditable_type', PaidCheckJourney::class)->where('auditable_id', $journey->id)
                ->where('event', 'paid_check_start_failed')->where('after_values->check_number', $waitingFor)->exists()) {
            $checks[] = ['number' => $waitingFor, 'status' => 'failed', 'score' => null, 'items' => [], 'missing_required' => [],
                'message' => 'Semakan tidak dapat dimulakan. Pegawai JKEJ akan menghubungi anda.'];
        }
        $open = PaidCheckJourneyService::amendmentOpen($journey);

        return [
            'state' => $journey->state,
            'checks' => $checks,
            'amendment_due_at' => $journey->amendment_due_at,
            'can_extend' => $open && $journey->extended_at === null,
            'can_amend' => $open,
            'can_submit' => $journey->state === PaidCheckState::AwaitingApplicant && $journey->amended_at === null,
        ];
    }

    /** Names of required checklist items with no document in the given check's document set. */
    public function missingRequired(PaidCheckJourney $journey, int $checkNumber): array
    {
        $version = ReviewVersion::query()->where('check_number', $checkNumber)
            ->whereHas('reviewCase', fn ($query) => $query->where('application_id', $journey->application_id))->first();
        $manifest = $version === null ? [] : (array) (SystemConfigSnapshot::query()->where('review_version_id', $version->id)->value('config_json')['document_manifest'] ?? []);
        $presentTypes = ApplicationDocument::query()->whereIn('id', array_column($manifest, 'id'))->pluck('document_type_id')->all();
        $draft = $journey->draft;
        $type = $draft->application_type_id;

        return $this->rules->documentsFor($type, $draft->particulars())
            ->filter(fn (array $item) => $item['is_required'])
            ->reject(fn (array $item) => in_array(DocumentType::query()->where('code', DocumentCatalogueService::documentTypeCode($type, $item['code']))->value('id'), $presentTypes, true))
            ->pluck('name')->values()->all();
    }

    private function check(PaidCheckJourney $journey, ReviewCase $case, ReviewVersion $version): array
    {
        $status = match ($version->status) {
            'COMPLETED' => 'completed',
            'FAILED' => 'failed',
            default => 'running',
        };
        $message = null;
        if ($status === 'failed') {
            $message = $case->error_code === 'MATRIX_UNAVAILABLE'
                ? 'Semakan sedang diulang semula secara automatik.'
                : 'Semakan tidak dapat diselesaikan. Pegawai JKEJ akan menghubungi anda.';
        }
        $items = [];
        if ($status === 'completed') {
            $report = GeneratedReport::query()->where('review_version_id', $version->id)->latest()->first();
            foreach ($report?->report_json['sections'] ?? [] as $section) {
                foreach ($section['findings'] ?? [] as $finding) {
                    $items[] = [
                        'section' => (string) ($section['title'] ?? ''),
                        'title' => (string) ($finding['title'] ?? ''),
                        'status_label' => self::STATUS_LABELS[$finding['status'] ?? ''] ?? 'Akan disemak oleh pegawai',
                        'standard_comment' => $finding['standard_comment'] ?? null,
                        'evidence' => collect($finding['evidence'] ?? [])->filter(fn ($evidence) => ! empty($evidence['source_document_id']))
                            ->map(fn ($evidence) => ['document' => (string) ($evidence['document'] ?? ''), 'page' => $evidence['page'] ?? null,
                                'document_id' => (string) $evidence['source_document_id']])->values()->all(),
                    ];
                }
            }
        }

        return [
            'number' => (int) $version->check_number,
            'status' => $status,
            'message' => $message,
            'score' => $status === 'completed' ? $version->score : null,
            'items' => $items,
            'missing_required' => $status === 'completed' ? $this->missingRequired($journey, (int) $version->check_number) : [],
        ];
    }
}
