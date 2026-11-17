<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\EvaluationMethod;
use App\Airs\Models\ReviewRule;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

final class RuleRepository
{
    /** @return Collection<int, ReviewRule> */
    public function syncConfiguredPack(): Collection
    {
        return $this->syncPack((string) config('airs.rule_pack'), (string) config('airs.rule_version'));
    }

    /** @return Collection<int, ReviewRule> */
    public function syncPack(string $pack, string $version): Collection
    {
        $payload = $this->loadVersion($pack, $version);
        $this->validate($payload);
        $manifest = $payload['manifest'];

        foreach ($payload['rules'] as $rule) {
            $hash = hash('sha256', json_encode($rule, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $existing = ReviewRule::query()
                ->where('rule_pack', $manifest['rule_pack'])
                ->where('rule_version', $manifest['rule_version'])
                ->where('rule_id', $rule['rule_id'])
                ->first();
            if ($existing !== null
                && $existing->lifecycle_status === 'DITERBITKAN'
                && $existing->rule_hash !== $hash) {
                throw new RuntimeException("Published knowledge rule cannot be changed in-place: {$rule['rule_id']}. Create a new rule version instead.");
            }
            $metadata = $this->metadataForRule($manifest, $rule);
            ReviewRule::updateOrCreate(
                [
                    'rule_pack' => $manifest['rule_pack'],
                    'rule_version' => $manifest['rule_version'],
                    'rule_id' => $rule['rule_id'],
                ],
                $rule + $metadata + ['rule_hash' => $hash],
            );
        }

        return ReviewRule::query()
            ->where('rule_pack', $pack)
            ->where('rule_version', $version)
            ->where('active', true)
            ->orderBy('rule_id')
            ->get();
    }

    private function loadVersion(string $pack, string $version): array
    {
        if (preg_match('/^[a-z0-9_-]+$/', $pack) !== 1) {
            throw new RuntimeException('Configured knowledge-pack name is invalid.');
        }
        if (preg_match('/^v[0-9]+$/', $version) !== 1) {
            throw new RuntimeException('Configured knowledge-pack version is invalid.');
        }

        $path = base_path("knowledge/{$pack}/rules.{$version}.json");
        if (! is_file($path)) {
            throw new RuntimeException("Configured knowledge pack was not found: {$version}.");
        }

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! isset($payload['extends'])) {
            return $payload;
        }

        $base = $this->loadVersion($pack, (string) $payload['extends']);
        $rules = collect($base['rules'])->keyBy('rule_id');
        foreach ($payload['overrides'] ?? [] as $override) {
            $ruleId = (string) ($override['rule_id'] ?? '');
            if (! $rules->has($ruleId)) {
                throw new RuntimeException("Knowledge-pack override refers to an unknown rule: {$ruleId}.");
            }
            $rules->put($ruleId, array_replace($rules->get($ruleId), $override));
        }
        foreach ($payload['rules'] ?? [] as $rule) {
            $rules->put((string) ($rule['rule_id'] ?? ''), $rule);
        }

        return [
            'manifest' => $payload['manifest'],
            'rules' => $rules->values()->all(),
        ];
    }

    public function validate(array $payload): void
    {
        if (! isset($payload['manifest']['rule_pack'], $payload['manifest']['rule_version'], $payload['rules']) || ! is_array($payload['rules'])) {
            throw new RuntimeException('Knowledge pack manifest is invalid.');
        }
        $status = (string) ($payload['manifest']['lifecycle_status'] ?? $payload['manifest']['status'] ?? 'DRAF');
        if (! in_array($status, ['DRAF', 'SEMAKAN', 'DITERBITKAN', 'DITAMATKAN', 'development_starter_catalogue', 'officer_review_calibrated_pilot'], true)) {
            throw new RuntimeException("Knowledge pack lifecycle status is invalid: {$status}.");
        }

        $ids = [];
        foreach ($payload['rules'] as $index => $rule) {
            foreach (['rule_id', 'title', 'section', 'requirement', 'evaluation_method', 'required_evidence_types', 'applies_when'] as $key) {
                if (! array_key_exists($key, $rule)) {
                    throw new RuntimeException("Knowledge rule {$index} is missing {$key}.");
                }
            }
            if (isset($ids[$rule['rule_id']])) {
                throw new RuntimeException("Duplicate knowledge rule ID: {$rule['rule_id']}.");
            }
            if (EvaluationMethod::tryFrom((string) $rule['evaluation_method']) === null) {
                throw new RuntimeException("Invalid evaluation method for {$rule['rule_id']}.");
            }
            $ids[$rule['rule_id']] = true;
        }
    }

    private function metadataForRule(array $manifest, array $rule): array
    {
        $tags = $this->applicabilityTags($rule);

        return [
            'category' => $this->primaryCategory($tags, $rule),
            'applicability_tags' => $tags,
            'evidence_required' => ($rule['required_evidence_types'] ?? []) !== [],
            'lifecycle_status' => $this->normaliseLifecycleStatus((string) ($manifest['lifecycle_status'] ?? $manifest['status'] ?? 'DRAF')),
            'effective_from' => $manifest['effective_from'] ?? null,
            'published_at' => $manifest['published_at'] ?? null,
            'retired_at' => $manifest['retired_at'] ?? null,
            'change_reason' => $manifest['change_reason'] ?? null,
        ];
    }

    /**
     * @return list<string>
     */
    private function applicabilityTags(array $rule): array
    {
        $tags = [];
        $ruleId = (string) ($rule['rule_id'] ?? '');
        $conditions = json_encode($rule['applies_when'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if ($conditions === '[]' || $conditions === '{}') {
            $tags[] = 'UMUM';
        }
        if (str_contains($conditions, 'BESI')) {
            $tags[] = 'BESI';
        }
        if (str_contains($conditions, 'KONKRIT') || str_contains($conditions, '"contains":"TNB"')) {
            $tags[] = 'KONKRIT';
        }
        if (str_contains($conditions, 'SOLAR')) {
            $tags[] = 'SOLAR';
        }
        if ($conditions !== '[]' && $conditions !== '{}') {
            $tags[] = 'BERSYARAT';
        }
        if (($rule['evaluation_method'] ?? '') === EvaluationMethod::Manual->value || (bool) ($rule['manual_confirmation_required'] ?? false)) {
            $tags[] = 'MANUAL_ONLY';
        }
        if (str_starts_with($ruleId, 'LJUP-GEN-')) {
            $tags[] = 'UMUM';
        }
        if (str_starts_with($ruleId, 'LJUP-SPC-')) {
            $tags[] = 'KHUSUS';
        }

        return array_values(array_unique($tags));
    }

    private function primaryCategory(array $tags, array $rule): string
    {
        foreach (['KONKRIT', 'BESI', 'SOLAR', 'BERSYARAT', 'MANUAL_ONLY', 'KHUSUS', 'UMUM'] as $candidate) {
            if (in_array($candidate, $tags, true)) {
                return $candidate;
            }
        }

        return str_starts_with((string) ($rule['rule_id'] ?? ''), 'LJUP-XDOC-') ? 'BERSYARAT' : 'UMUM';
    }

    private function normaliseLifecycleStatus(string $status): string
    {
        return match ($status) {
            'development_starter_catalogue' => 'DRAF',
            'officer_review_calibrated_pilot' => 'SEMAKAN',
            default => $status,
        };
    }
}
