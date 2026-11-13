<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Contracts\AiProvider;
use App\Airs\Enums\EvaluationMethod;
use App\Airs\Enums\FindingStatus;
use App\Airs\Exceptions\InvalidAiResponseException;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ReviewEvidence;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewPage;
use App\Airs\Models\ReviewRule;
use App\Airs\Models\ReviewVersion;
use App\Airs\Ai\AiResponseValidator;
use App\Airs\Ai\AiTaskRunner;

final class RuleEvaluationService
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiTaskRunner $runner,
        private readonly AiResponseValidator $validator,
        private readonly RuleApplicabilityService $applicability,
        private readonly EvidenceRetriever $retriever,
        private readonly FindingPolicy $policy,
        private readonly OfficerReferenceCalibrationService $officerCalibration,
    ) {
    }

    /**
     * Only an exhausted check budget leaves a rule unassessed (manual review). Worker faults,
     * timeouts and outages fail the check instead, so it is retried without using up a paid check.
     */
    private const UNASSESSABLE_MATRIX_CODES = ['MATRIX_REVIEW_BUDGET'];

    public function evaluate(ReviewVersion $version, ApplicationProfile $profile, iterable $rules): void
    {
        foreach ($rules as $rule) {
            $availableEvidence = $this->retriever->forRule($version, $rule);
            $calibrated = $this->officerCalibration->forRule($version, $profile, $rule, $availableEvidence);
            $applicable = $calibrated['applicable'] ?? $this->applicability->evaluate($rule, $profile->profile_json);
            $applicabilityExplanation = $this->applicability->explain($rule, $profile->profile_json);

            if ($calibrated !== null) {
                $result = $calibrated['result'];
            } elseif ($applicable === false) {
                $result = $this->base(FindingStatus::NotApplicable, 'The configured applicability condition is not met.');
            } elseif ($applicable === null) {
                $result = $this->base(FindingStatus::ManualReview, 'Applicability could not be determined from the digital evidence.');
                $result['manual_review_reason'] = 'A required application-profile value is unknown.';
            } elseif ($rule->evaluation_method === EvaluationMethod::Manual) {
                $result = $this->manual($rule, $availableEvidence);
            } elseif ($rule->evaluation_method === EvaluationMethod::Deterministic) {
                $result = $this->deterministic($rule, $version, $profile, $availableEvidence);
            } elseif ($rule->evaluation_method === EvaluationMethod::CrossDocument) {
                $result = $this->crossDocument($rule, $version, $profile, $availableEvidence);
            } else {
                $result = $this->ai($rule, $version, $profile, $availableEvidence);
            }

            $result = $this->policy->enforce($rule, $result, $availableEvidence);
            $result['extracted_values']['applicability'] = $applicabilityExplanation;
            $this->store($version, $rule, $applicable, $result);
        }
    }

    private function deterministic(ReviewRule $rule, ReviewVersion $version, ApplicationProfile $profile, array $evidence): array
    {
        if ($rule->rule_id === 'LJUP-SUB-007') {
            $photoPages = $version->classifications()->where('primary_type', 'SITE_PHOTOGRAPHS')->with('file')->get()->sum(fn ($item): int => max(1, (int) $item->file->page_count));
            if ($photoPages >= 4) {
                return $this->base(FindingStatus::Complied, "{$photoPages} directly uploaded photograph pages were detected.", $evidence);
            }
            if ($photoPages > 0) {
                return $this->base(FindingStatus::NotComplied, "Only {$photoPages} directly uploaded photograph page(s) were detected; four are required.", $evidence);
            }

            return $this->base(FindingStatus::NotDetected, 'No direct site-photograph file was detected. Checklist or cover-letter mentions were not counted.');
        }

        if (in_array($rule->rule_id, ['LJUP-SUB-008', 'LJUP-SUB-009'], true)) {
            $result = $this->base(
                FindingStatus::OfficerManualReview,
                $evidence === []
                    ? 'No payment document was detected.'
                    : 'A payment registration/demand document was detected; paid status is not independently established.',
                $evidence,
            );
            $result['manual_review_reason'] = 'Payment settlement must be checked against an independent authoritative source.';

            return $result;
        }

        $requiredTypes = $rule->required_evidence_types ?? [];
        $foundTypes = array_values(array_unique(array_column($evidence, 'document_type')));
        $complete = $requiredTypes !== [] && array_diff($requiredTypes, $foundTypes) === [];
        if ($complete && ! $rule->manual_confirmation_required) {
            return $this->base(FindingStatus::Complied, 'All required document types were directly detected in the uploaded package.', $evidence);
        }
        if ($evidence !== []) {
            $status = $this->requiresOfficerManualReview($rule)
                ? FindingStatus::OfficerManualReview
                : FindingStatus::ManualReview;
            $result = $this->base(
                $status,
                $status === FindingStatus::OfficerManualReview
                    ? 'Relevant documents were detected; official approval or authenticity requires officer verification.'
                    : 'Relevant documents were detected, but the digital review still needs manual checking.',
                $evidence,
            );
            $result['manual_review_reason'] = $status === FindingStatus::OfficerManualReview
                ? 'Document presence does not prove official approval or authenticity.'
                : 'Digital evidence is not sufficient for a final technical conclusion.';

            return $result;
        }

        return $this->base(FindingStatus::NotDetected, 'The required direct document evidence was not detected.');
    }

    private function crossDocument(ReviewRule $rule, ReviewVersion $version, ApplicationProfile $profile, array $evidence): array
    {
        if ($rule->rule_id === 'LJUP-XDOC-001') {
            $schedules = collect($profile->profile_json['lamp_schedules'] ?? []);
            $plan = $schedules->firstWhere('source_type', 'LJUP_PLAN');
            $photo = $schedules->firstWhere('source_type', 'PHOTOMETRIC_REPORT');
            if ($plan && $photo) {
                $requiresManualVerification = (bool) ($plan['requires_manual_verification'] ?? false)
                    || (bool) ($photo['requires_manual_verification'] ?? false);
                $scheduleEvidence = collect([$plan, $photo])->map(fn (array $schedule): array => [
                    'file_id' => $schedule['file_id'],
                    'page_number' => $schedule['page_number'] ?? null,
                    'quote_or_value' => json_encode($schedule['quantities'], JSON_THROW_ON_ERROR),
                    'evidence_type' => 'VALUE',
                    'confidence' => 0.9,
                ])->all();
                if ($requiresManualVerification) {
                    $result = $this->base(
                        FindingStatus::ManualReview,
                        'Lamp quantities were normalised for comparison, but at least one source requires manual verification before compliance can be confirmed.',
                        $scheduleEvidence,
                        ['plan' => $plan['quantities'], 'photometric' => $photo['quantities']],
                    );
                    $result['manual_review_reason'] = 'One or more lamp schedules were inferred from another document because direct readable plan text was not available.';

                    return $result;
                }
                if (($plan['quantities'] ?? []) === ($photo['quantities'] ?? [])) {
                    return $this->base(FindingStatus::Complied, 'Lamp wattage and quantity values match between the plan and photometric report.', $scheduleEvidence, ['quantities' => $plan['quantities']]);
                }

                return $this->base(FindingStatus::NotComplied, 'Lamp wattage or quantity values conflict between the plan and photometric report.', $scheduleEvidence, ['plan' => $plan['quantities'], 'photometric' => $photo['quantities']]);
            }

            return $this->base(FindingStatus::NotDetected, 'Readable lamp schedules were not extracted from both the plan and photometric report.');
        }

        if ($rule->rule_id === 'LJUP-XDOC-006') {
            $declared = collect($profile->profile_json['declared_attachments'] ?? [])->contains('name', 'SITE_PHOTOGRAPHS');
            $actual = in_array('SITE_PHOTOGRAPHS', $profile->profile_json['actual_document_types'] ?? [], true);
            if ($declared && ! $actual) {
                return $this->base(FindingStatus::NotComplied, 'Site photographs were declared but no direct photograph file was detected. Declaration text was not treated as the attachment.', $evidence);
            }
            if ($declared && $actual) {
                return $this->base(FindingStatus::Complied, 'The declared site-photograph attachment is directly present.', $evidence);
            }

            return $this->base(FindingStatus::ManualReview, 'No reliable declared-versus-present attachment comparison could be completed.', $evidence);
        }

        if ($rule->rule_id === 'LJUP-XDOC-007') {
            $result = $this->base(FindingStatus::OfficerManualReview, 'Payment documents are recorded as registration/demand evidence only; amount and settlement require officer verification.', $evidence);
            $result['manual_review_reason'] = 'Submission round and official paid status are not independently available.';

            return $result;
        }

        $result = $this->base(FindingStatus::ManualReview, 'Relevant cross-document evidence was located but the configured comparison requires additional structured values or officer review.', $evidence);
        $result['manual_review_reason'] = 'The deterministic comparison does not have enough reliable normalised values.';

        return $result;
    }

    private function manual(ReviewRule $rule, array $evidence): array
    {
        $status = $this->requiresOfficerManualReview($rule)
            ? FindingStatus::OfficerManualReview
            : FindingStatus::ManualReview;
        $result = $this->base(
            $status,
            $status === FindingStatus::OfficerManualReview
                ? 'This requirement needs officer verification for an official document or authority matter.'
                : 'This requirement needs manual technical checking.',
            $evidence,
        );
        $result['manual_review_reason'] = $status === FindingStatus::OfficerManualReview
            ? 'Authenticity, approval status, payment status, stamp, or signature must be verified by an authorised officer.'
            : 'Manual checking is needed, but it is not limited to authorised-officer verification.';

        return $result;
    }

    private function requiresOfficerManualReview(ReviewRule $rule): bool
    {
        return in_array($rule->rule_id, [
            'LJUP-SUB-002',
            'LJUP-SUB-003',
            'LJUP-SUB-004',
            'LJUP-SUB-005',
            'LJUP-SUB-008',
            'LJUP-SUB-009',
            'LJUP-SUB-010',
            'LJUP-XDOC-007',
        ], true);
    }

    private function ai(ReviewRule $rule, ReviewVersion $version, ApplicationProfile $profile, array $evidence): array
    {
        $input = [
            'rule' => $rule->only(['rule_id', 'title', 'requirement', 'source_document', 'source_page', 'manual_confirmation_required', 'evaluation_method']),
            'application_profile' => $profile->profile_json,
            'evidence' => $evidence,
            'reference_documents' => [],
        ];
        try {
            return $this->runner->run(
                $version,
                'evaluate_rule',
                'review_finding_v1',
                $input,
                fn (array $payload): array => $this->provider->evaluateRule($payload),
                fn (array $response): array => $this->validator->finding($response),
                $rule->rule_id,
            );
        } catch (InvalidAiResponseException) {
            $result = $this->base(FindingStatus::ManualReview, 'The AI result was invalid and was not stored as a review conclusion.', $evidence);
            $result['manual_review_reason'] = 'The structured-response schema was not satisfied.';
            $result['warnings'] = ['AI_RESPONSE_INVALID'];

            return $result;
        } catch (\App\Airs\Exceptions\MatrixTaskException $exception) {
            $code = $exception->getMessage();
            if (! in_array($code, self::UNASSESSABLE_MATRIX_CODES, true)) {
                throw $exception;
            }
            $result = $this->base(FindingStatus::ManualReview, 'This rule was not assessed by MATRIX and needs manual checking.', $evidence);
            $result['manual_review_reason'] = 'Tidak dapat dinilai dalam had kos/masa MATRIX.';
            $result['warnings'] = [$code];

            return $result;
        }
    }

    private function store(ReviewVersion $version, ReviewRule $rule, ?bool $applicable, array $result): void
    {
        ReviewFinding::query()
            ->where('review_version_id', $version->id)
            ->where('rule_id', $rule->rule_id)
            ->where('is_superseded', false)
            ->update(['is_superseded' => true]);

        $finding = ReviewFinding::create([
            'review_version_id' => $version->id,
            'review_rule_id' => $rule->id,
            'rule_id' => $rule->rule_id,
            'applicable' => $applicable,
            'status' => $result['status'],
            'confidence' => $result['confidence'],
            'finding' => $result['finding'],
            'extracted_values' => $result['extracted_values'],
            'manual_review_reason' => $result['manual_review_reason'],
            'warnings' => $result['warnings'],
            'evaluation_method' => $rule->evaluation_method,
            'ai_run_id' => $result['_ai_run_id'] ?? null,
            'is_superseded' => false,
        ]);

        foreach ($result['evidence'] as $item) {
            $page = isset($item['page_number']) ? ReviewPage::query()
                ->where('review_file_id', $item['file_id'])
                ->where('page_number', $item['page_number'])
                ->first() : null;
            ReviewEvidence::create([
                'review_finding_id' => $finding->id,
                'review_file_id' => $item['file_id'],
                'review_page_id' => $page?->id,
                'page_number' => $item['page_number'] ?? null,
                'quote_or_value' => $item['quote_or_value'],
                'evidence_type' => $item['evidence_type'],
                'confidence' => $item['confidence'] ?? null,
            ]);
        }
    }

    private function base(FindingStatus $status, string $finding, array $evidence = [], array $values = []): array
    {
        return [
            'status' => $status->value,
            'confidence' => $status === FindingStatus::Complied ? 0.9 : 0.7,
            'finding' => $finding,
            'extracted_values' => $values,
            'manual_review_reason' => null,
            'warnings' => [],
            'evidence' => array_map(fn (array $item): array => [
                'file_id' => $item['file_id'],
                'page_number' => $item['page_number'] ?? null,
                'quote_or_value' => (string) ($item['quote_or_value'] ?? ''),
                'evidence_type' => $item['evidence_type'] ?? 'TEXT',
                'confidence' => $item['confidence'] ?? null,
            ], $evidence),
        ];
    }
}
