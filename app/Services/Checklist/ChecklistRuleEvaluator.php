<?php

declare(strict_types=1);

namespace App\Services\Checklist;

use App\Models\DocumentRequirement;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * ADR-362: applies the published Bangunan catalogue's conditional rules to a
 * project's particulars. Deterministic: no AI, no guessing. A condition key that
 * is set must match; a particular that is unknown never satisfies a condition.
 */
final class ChecklistRuleEvaluator
{
    public const LIST_KEYS = ['work_types' => 'work_type', 'building_categories' => 'building_category', 'site_conditions' => 'site_conditions'];

    /** Validation rules for a conditions object at $prefix (e.g. "items.*.conditions"). */
    public static function conditionRules(string $prefix): array
    {
        $rules = [$prefix => ['nullable', 'array:work_types,building_categories,storeys_min,floor_area_min,site_conditions']];
        foreach (array_keys(self::LIST_KEYS) as $key) {
            $rules["{$prefix}.{$key}"] = ['nullable', 'array'];
            $rules["{$prefix}.{$key}.*"] = ['string', 'distinct', Rule::in(array_keys(config("kaunter.{$key}", [])))];
        }
        $rules["{$prefix}.storeys_min"] = ['nullable', 'integer', 'min:1', 'max:100'];
        $rules["{$prefix}.floor_area_min"] = ['nullable', 'numeric', 'gt:0', 'max:1000000'];

        return $rules;
    }

    /** Drops empty keys; returns null when the item has no condition (always applies). */
    public static function normalise(?array $conditions): ?array
    {
        $clean = [];
        foreach (array_keys(self::LIST_KEYS) as $key) {
            $values = array_values(array_filter((array) ($conditions[$key] ?? []), fn ($value) => $value !== null && $value !== ''));
            if ($values !== []) {
                $clean[$key] = $values;
            }
        }
        if (($conditions['storeys_min'] ?? null) !== null && $conditions['storeys_min'] !== '') {
            $clean['storeys_min'] = (int) $conditions['storeys_min'];
        }
        if (($conditions['floor_area_min'] ?? null) !== null && $conditions['floor_area_min'] !== '') {
            $clean['floor_area_min'] = (float) $conditions['floor_area_min'];
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * @param  array{work_type?: ?string, building_category?: ?string, storeys?: ?int, floor_area?: ?float, site_conditions?: list<string>}  $particulars
     * @return Collection<int, array{code: string, name: string, guidance: ?string, is_required: bool, conditional: bool}>
     */
    public function documentsFor(string $applicationTypeId, array $particulars): Collection
    {
        return DocumentRequirement::query()->with('documentType')->where('application_type_id', $applicationTypeId)
            ->where('is_active', true)->whereNull('permission_category')->whereNull('permission_type')
            ->orderBy('sort_order')->get()
            ->filter(fn (DocumentRequirement $item) => self::matches($item->conditions, $particulars))
            ->map(fn (DocumentRequirement $item) => [
                'code' => \App\Services\DocumentCatalogueService::itemCode($applicationTypeId, $item->documentType->code),
                'name' => $item->documentType->name,
                'guidance' => $item->guidance,
                'is_required' => $item->is_required,
                'conditional' => $item->conditions !== null && $item->conditions !== [],
                'keywords' => array_values($item->precheck_keywords ?? []),
            ])->values();
    }

    /** Plain Malay summary, e.g. "Wajib jika: Tambahan / pembesaran · 3 tingkat ke atas". */
    public static function describe(?array $conditions): string
    {
        $parts = [];
        foreach (array_keys(self::LIST_KEYS) as $key) {
            if (! empty($conditions[$key])) {
                $labels = array_map(fn ($value) => config("kaunter.{$key}.{$value}", $value), (array) $conditions[$key]);
                $parts[] = implode(' / ', $labels);
            }
        }
        if (! empty($conditions['storeys_min'])) {
            $parts[] = (int) $conditions['storeys_min'].' tingkat ke atas';
        }
        if (! empty($conditions['floor_area_min'])) {
            $parts[] = rtrim(rtrim(number_format((float) $conditions['floor_area_min'], 2, '.', ''), '0'), '.').' m² ke atas';
        }

        return $parts === [] ? 'Sentiasa diperlukan' : 'Wajib jika: '.implode(' · ', $parts);
    }

    public static function matches(?array $conditions, array $particulars): bool
    {
        foreach ($conditions ?? [] as $key => $expected) {
            $ok = match ($key) {
                'work_types', 'building_categories' => in_array($particulars[self::LIST_KEYS[$key]] ?? null, (array) $expected, true),
                'site_conditions' => array_intersect((array) ($particulars['site_conditions'] ?? []), (array) $expected) !== [],
                'storeys_min' => isset($particulars['storeys']) && (int) $particulars['storeys'] >= (int) $expected,
                'floor_area_min' => isset($particulars['floor_area']) && (float) $particulars['floor_area'] >= (float) $expected,
                default => false,
            };
            if (! $ok) {
                return false;
            }
        }

        return true;
    }
}
