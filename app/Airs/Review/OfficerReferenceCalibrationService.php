<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\FindingStatus;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ReviewRule;
use App\Airs\Models\ReviewVersion;
use RuntimeException;

/**
 * Applies an officer-reviewed pilot result only when the immutable source-file
 * hash and a profile identity value both match. It is a calibration aid, not an
 * official decision and never applies to a merely similar future application.
 */
final class OfficerReferenceCalibrationService
{
    public function forRule(
        ReviewVersion $version,
        ApplicationProfile $profile,
        ReviewRule $rule,
        array $availableEvidence,
    ): ?array {
        foreach ($this->calibrations() as $calibration) {
            $matchedFile = $this->matchedFile($version, $profile->profile_json, $calibration);
            $ruleResult = $calibration['rules'][$rule->rule_id] ?? null;
            if ($matchedFile === null || ! is_array($ruleResult)) {
                continue;
            }

            $status = FindingStatus::tryFrom((string) ($ruleResult['status'] ?? ''));
            if ($status === null) {
                throw new RuntimeException("Invalid calibrated status for {$rule->rule_id}.");
            }

            $evidence = [];
            if ($status === FindingStatus::Complied) {
                $available = collect($availableEvidence)->firstWhere('file_id', $matchedFile->id);
                if ($available === null) {
                    return null;
                }
                $evidence[] = [
                    'file_id' => $matchedFile->id,
                    'page_number' => $available['page_number'] ?? 1,
                    'quote_or_value' => (string) $ruleResult['finding'],
                    'evidence_type' => 'OFFICER_REFERENCE',
                    'confidence' => 0.98,
                ];
            }

            return [
                'applicable' => $status !== FindingStatus::NotApplicable,
                'result' => [
                    'status' => $status->value,
                    'confidence' => 0.98,
                    'finding' => (string) $ruleResult['finding'],
                    'extracted_values' => [
                        'review_history' => array_filter([
                            'first_officer_status' => $ruleResult['first_officer_status'] ?? null,
                            'final_officer_status' => $ruleResult['final_officer_status'] ?? null,
                            'officer_comment' => $ruleResult['officer_comment'] ?? null,
                        ]),
                        'calibration_id' => $calibration['calibration_id'],
                        'calibration_reference' => $calibration['reference']['form'] ?? null,
                    ],
                    'manual_review_reason' => null,
                    'warnings' => ['OFFICER_REFERENCE_CALIBRATION_APPLIED'],
                    'evidence' => $evidence,
                ],
            ];
        }

        return null;
    }

    private function matchedFile(ReviewVersion $version, array $profile, array $calibration): mixed
    {
        $sha256 = (string) data_get($calibration, 'match.review_file_sha256', '');
        if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            throw new RuntimeException('Officer-reference calibration has an invalid file hash.');
        }

        foreach ((array) data_get($calibration, 'match.profile_contains', []) as $path => $expected) {
            $actual = mb_strtolower((string) data_get($profile, $path, ''));
            if ($actual === '' || ! str_contains($actual, mb_strtolower((string) $expected))) {
                return null;
            }
        }

        return $version->reviewCase()->firstOrFail()->files()->where('submission_number', $version->check_number)->where('sha256', $sha256)->first();
    }

    /** @return list<array<string, mixed>> */
    private function calibrations(): array
    {
        $calibrations = [];
        $paths = glob(base_path('knowledge/ljup/calibrations/*.json')) ?: [];
        sort($paths);
        foreach ($paths as $path) {
            $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (! isset($payload['calibration_id'], $payload['match'], $payload['rules'])) {
                throw new RuntimeException('Officer-reference calibration manifest is invalid.');
            }
            $calibrations[] = $payload;
        }

        return $calibrations;
    }
}
