<?php

declare(strict_types=1);

namespace Tests\Unit\Airs;

use App\Airs\Exceptions\InvalidAiResponseException;
use App\Airs\Ai\AiResponseValidator;
use PHPUnit\Framework\TestCase;

final class AiResponseValidatorTest extends TestCase
{
    public function test_invalid_finding_status_is_rejected(): void
    {
        $this->expectException(InvalidAiResponseException::class);

        (new AiResponseValidator)->finding([
            'status' => 'APPROVED',
            'confidence' => 0.9,
            'finding' => 'Invalid official decision.',
            'extracted_values' => [],
            'manual_review_reason' => null,
            'warnings' => [],
            'evidence' => [],
        ]);
    }

    public function test_missing_required_classification_field_is_rejected(): void
    {
        $this->expectException(InvalidAiResponseException::class);

        (new AiResponseValidator)->classification(['primary_type' => 'LJUP_PLAN']);
    }
}
