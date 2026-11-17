<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Models\ReviewRule;

final class RuleApplicabilityService
{
    /**
     * @return array{applicable:?bool, reason:string, source:string, matched_values:list<string>, conditions:array<string, mixed>}
     */
    public function explain(ReviewRule $rule, array $profile): array
    {
        $applicable = $this->evaluate($rule, $profile);
        $conditions = $rule->applies_when ?? [];
        $selected = array_values(array_filter((array) ($profile['selected_lighting_type_labels'] ?? $profile['selected_lighting_types'] ?? [])));

        if ($conditions === []) {
            return [
                'applicable' => true,
                'reason' => 'Digunakan untuk semua permohonan LJUP.',
                'source' => 'GENERAL_RULE',
                'matched_values' => [],
                'conditions' => [],
            ];
        }

        if ($selected !== []) {
            $selectedText = implode(', ', array_map('strval', $selected));
            $reason = match ($applicable) {
                true => "Digunakan kerana pilihan jenis lampu pemohon: {$selectedText}.",
                false => "Tidak digunakan berdasarkan pilihan jenis lampu pemohon: {$selectedText}.",
                null => "Kebolehgunaan belum dapat dipastikan walaupun pilihan jenis lampu pemohon ialah: {$selectedText}.",
            };

            return [
                'applicable' => $applicable,
                'reason' => $reason,
                'source' => 'SUBMITTED_LIGHTING_TYPES',
                'matched_values' => array_map('strval', $selected),
                'conditions' => $conditions,
            ];
        }

        $lightingSystem = (string) ($profile['lighting_system'] ?? '');
        if ($lightingSystem !== '' && $lightingSystem !== 'UNKNOWN') {
            $reason = match ($applicable) {
                true => "Digunakan berdasarkan sistem lampu yang dikesan dalam dokumen: {$lightingSystem}.",
                false => "Tidak digunakan berdasarkan sistem lampu yang dikesan dalam dokumen: {$lightingSystem}.",
                null => "Kebolehgunaan belum dapat dipastikan daripada sistem lampu yang dikesan: {$lightingSystem}.",
            };

            return [
                'applicable' => $applicable,
                'reason' => $reason,
                'source' => 'DETECTED_LIGHTING_SYSTEM',
                'matched_values' => [$lightingSystem],
                'conditions' => $conditions,
            ];
        }

        return [
            'applicable' => $applicable,
            'reason' => $applicable === null
                ? 'Kebolehgunaan tidak dapat ditentukan kerana pilihan jenis lampu atau sistem lampu tidak tersedia.'
                : 'Kebolehgunaan ditentukan melalui syarat Rule Registry.',
            'source' => 'RULE_REGISTRY',
            'matched_values' => [],
            'conditions' => $conditions,
        ];
    }

    public function evaluate(ReviewRule $rule, array $profile): ?bool
    {
        $conditions = $rule->applies_when ?? [];
        if ($conditions !== [] && ($profile['lighting_type_conflict'] ?? false)) {
            return null;
        }
        if ($conditions === []) {
            return true;
        }

        if (isset($conditions['any_of']) && is_array($conditions['any_of'])) {
            return $this->evaluateAnyOf($conditions['any_of'], $profile);
        }

        if (isset($conditions['all_of']) && is_array($conditions['all_of'])) {
            return $this->evaluateAllOf($conditions['all_of'], $profile);
        }

        foreach ($conditions as $path => $expected) {
            $actual = data_get($profile, $path, '__MISSING__');
            if ($actual === '__MISSING__' || $actual === 'UNKNOWN' || $actual === null) {
                return null;
            }
            if (! $this->matches($actual, $expected)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $branches
     */
    private function evaluateAnyOf(array $branches, array $profile): ?bool
    {
        // LJUP v2 expresses new selections plus a legacy fallback as a two-branch OR.
        // Choose the applicable representation before applying generic three-valued OR.
        $selectedBranches = array_values(array_filter($branches, fn (array $branch): bool => count($branch) === 1 && array_key_exists('selected_lighting_types', $branch)));
        $legacyBranches = array_values(array_filter($branches, fn (array $branch): bool => count($branch) === 1 && array_key_exists('lighting_system', $branch)));
        if (count($branches) === 2 && count($selectedBranches) === 1 && count($legacyBranches) === 1) {
            if (! empty($profile['selected_lighting_types']) && is_array($profile['selected_lighting_types'])) {
                return $this->evaluateConditions($selectedBranches[0], $profile);
            }
            $legacy = $profile['lighting_system'] ?? null;
            if (is_string($legacy) && trim($legacy) !== '' && $legacy !== 'UNKNOWN') {
                return $this->evaluateConditions($legacyBranches[0], $profile);
            }
        }
        $hasUnknown = false;
        $hasFalse = false;

        foreach ($branches as $branch) {
            $result = $this->evaluateConditions($branch, $profile);
            if ($result === true) {
                return true;
            }
            if ($result === null) {
                $hasUnknown = true;
            } else {
                $hasFalse = true;
            }
        }

        if ($hasUnknown) {
            return null;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $branches
     */
    private function evaluateAllOf(array $branches, array $profile): ?bool
    {
        $hasUnknown = false;

        foreach ($branches as $branch) {
            $result = $this->evaluateConditions($branch, $profile);
            if ($result === false) {
                return false;
            }
            if ($result === null) {
                $hasUnknown = true;
            }
        }

        return $hasUnknown ? null : true;
    }

    private function evaluateConditions(array $conditions, array $profile): ?bool
    {
        if ($conditions === []) {
            return true;
        }

        if (isset($conditions['any_of']) && is_array($conditions['any_of'])) {
            return $this->evaluateAnyOf($conditions['any_of'], $profile);
        }

        if (isset($conditions['all_of']) && is_array($conditions['all_of'])) {
            return $this->evaluateAllOf($conditions['all_of'], $profile);
        }

        foreach ($conditions as $path => $expected) {
            $actual = data_get($profile, $path, '__MISSING__');
            if ($actual === '__MISSING__' || $actual === 'UNKNOWN' || $actual === null) {
                return null;
            }
            if (! $this->matches($actual, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function matches(mixed $actual, mixed $expected): bool
    {
        if (! is_array($expected)) {
            return $actual === $expected;
        }

        $actualValues = is_array($actual)
            ? array_map(fn (mixed $value): string => mb_strtolower(trim((string) $value)), $actual)
            : [mb_strtolower(trim((string) $actual))];
        $actualText = implode(' ', $actualValues);
        if (array_key_exists('contains', $expected)) {
            $needle = mb_strtolower(trim((string) $expected['contains']));

            return is_array($actual)
                ? in_array($needle, $actualValues, true)
                : str_contains($actualText, $needle);
        }
        if (array_key_exists('not_contains', $expected)) {
            $needle = mb_strtolower(trim((string) $expected['not_contains']));

            return is_array($actual)
                ? ! in_array($needle, $actualValues, true)
                : ! str_contains($actualText, $needle);
        }
        if (array_key_exists('contains_any', $expected) && is_array($expected['contains_any'])) {
            $needles = array_map(fn (mixed $value): string => mb_strtolower(trim((string) $value)), $expected['contains_any']);

            return collect($needles)->contains(fn (string $needle): bool => is_array($actual)
                ? in_array($needle, $actualValues, true)
                : str_contains($actualText, $needle));
        }
        if (array_key_exists('in', $expected) && is_array($expected['in'])) {
            return in_array($actual, $expected['in'], true);
        }

        return false;
    }
}
