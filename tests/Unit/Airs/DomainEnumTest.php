<?php

declare(strict_types=1);

namespace Tests\Unit\Airs;

use App\Airs\Enums\FindingStatus;
use App\Airs\Enums\ReviewCaseStatus;
use PHPUnit\Framework\TestCase;

final class DomainEnumTest extends TestCase
{
    public function test_processing_states_are_stable_and_explicit(): void
    {
        self::assertSame([
            'UPLOADED', 'EXTRACTING', 'INVENTORY', 'EXTRACTING_TEXT', 'RENDERING', 'CLASSIFYING',
            'EXTRACTING_PROFILE', 'EVALUATING_RULES', 'CONSOLIDATING', 'GENERATING_REPORT', 'COMPLETED', 'FAILED',
        ], array_column(ReviewCaseStatus::cases(), 'value'));
    }

    public function test_finding_statuses_are_restricted_to_the_catalogue(): void
    {
        self::assertSame([
            'DITUNAIKAN', 'BELUM_DITUNAIKAN', 'TIDAK_BERKAITAN', 'TIDAK_DAPAT_DIKESAN',
            'PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI',
        ], array_column(FindingStatus::cases(), 'value'));
    }
}
