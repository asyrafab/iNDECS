<?php

declare(strict_types=1);

namespace Tests\Unit\Airs;

use App\Airs\Models\ReviewRule;
use App\Airs\Review\RuleApplicabilityService;
use Tests\TestCase;

final class RuleApplicabilityServiceTest extends TestCase
{
    public function test_contains_matches_selected_lighting_type_arrays(): void
    {
        $service = app(RuleApplicabilityService::class);
        $rule = $this->rule(['selected_lighting_types' => ['contains' => 'KONKRIT']]);

        self::assertTrue($service->evaluate($rule, ['selected_lighting_types' => ['BESI', 'KONKRIT']]));
        self::assertFalse($service->evaluate($rule, ['selected_lighting_types' => ['BESI', 'SOLAR']]));
    }

    public function test_contains_any_matches_besi_or_solar(): void
    {
        $service = app(RuleApplicabilityService::class);
        $rule = $this->rule(['selected_lighting_types' => ['contains_any' => ['BESI', 'SOLAR']]]);

        self::assertTrue($service->evaluate($rule, ['selected_lighting_types' => ['SOLAR']]));
        self::assertFalse($service->evaluate($rule, ['selected_lighting_types' => ['KONKRIT']]));
    }

    public function test_any_of_falls_back_to_legacy_lighting_system_when_selection_is_missing(): void
    {
        $service = app(RuleApplicabilityService::class);
        $rule = $this->rule([
            'any_of' => [
                ['selected_lighting_types' => ['contains' => 'KONKRIT']],
                ['lighting_system' => ['contains' => 'TNB']],
            ],
        ]);

        self::assertTrue($service->evaluate($rule, ['lighting_system' => 'Tiang konkrit TNB']));
        self::assertFalse($service->evaluate($rule, ['lighting_system' => 'Tiang besi galvani']));
        self::assertNull($service->evaluate($rule, []));
    }

    public function test_explain_returns_a_human_readable_reason(): void
    {
        $service = app(RuleApplicabilityService::class);
        $rule = $this->rule(['selected_lighting_types' => ['contains' => 'KONKRIT']]);

        $explanation = $service->explain($rule, [
            'selected_lighting_types' => ['KONKRIT'],
            'selected_lighting_type_labels' => ['Lampu konkrit'],
        ]);

        self::assertTrue($explanation['applicable']);
        self::assertSame('SUBMITTED_LIGHTING_TYPES', $explanation['source']);
        self::assertStringContainsString('Lampu konkrit', $explanation['reason']);
    }

    private function rule(array $appliesWhen): ReviewRule
    {
        return new ReviewRule(['applies_when' => $appliesWhen]);
    }
}
