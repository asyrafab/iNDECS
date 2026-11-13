<?php

declare(strict_types=1);

namespace App\Services\Amira;

/**
 * Keeps only project particulars that iNDECS itself offers (ADR-369). AMIRA's proposal is a suggestion the PSP
 * confirms; anything outside the configured options or ranges is dropped before it reaches a card or a form.
 */
final class ProposalSanitiser
{
    /**
     * @param  array{application_types: array, work_types: array, building_categories: array, site_conditions: array}  $options
     * @return array{application_type?: string, work_type?: string, building_category?: string, storeys?: int, floor_area?: int|float, site_conditions?: list<string>}|null
     */
    public static function clean(?array $proposal, array $options): ?array
    {
        if ($proposal === null) {
            return null;
        }
        $clean = [];
        foreach (['application_type' => 'application_types', 'work_type' => 'work_types', 'building_category' => 'building_categories'] as $key => $set) {
            if (is_string($proposal[$key] ?? null) && array_key_exists($proposal[$key], $options[$set] ?? [])) {
                $clean[$key] = $proposal[$key];
            }
        }
        $storeys = $proposal['storeys'] ?? null;
        if (is_int($storeys) && $storeys >= 1 && $storeys <= 100) {
            $clean['storeys'] = $storeys;
        }
        $area = $proposal['floor_area'] ?? null;
        if ((is_int($area) || is_float($area)) && $area >= 1 && $area <= 1000000) {
            $clean['floor_area'] = $area;
        }
        $conditions = array_values(array_unique(array_filter(is_array($proposal['site_conditions'] ?? null) ? $proposal['site_conditions'] : [],
            fn ($value) => is_string($value) && array_key_exists($value, $options['site_conditions'] ?? []))));
        if ($conditions !== []) {
            $clean['site_conditions'] = $conditions;
        }

        return $clean === [] ? null : $clean;
    }
}
