<?php

declare(strict_types=1);

namespace App\Airs\Ai;

use App\Airs\Enums\FindingStatus;
use App\Airs\Exceptions\InvalidAiResponseException;

final class AiResponseValidator
{
    public function classification(array $result): array
    {
        $this->requireKeys($result, ['primary_type', 'secondary_tags', 'confidence', 'reason', 'evidence_page_numbers', 'warnings']);
        if (! is_string($result['primary_type']) || ! is_array($result['secondary_tags']) || ! is_string($result['reason'])
            || ! is_array($result['evidence_page_numbers']) || ! is_array($result['warnings'])) {
            throw new InvalidAiResponseException('Classification response has invalid field types.');
        }
        $this->confidence($result['confidence']);

        return $result;
    }

    public function profile(array $result): array
    {
        $this->requireKeys($result, ['profile', 'confidence_summary', 'conflicts']);
        if (! is_array($result['profile']) || ! is_array($result['confidence_summary']) || ! is_array($result['conflicts'])) {
            throw new InvalidAiResponseException('Profile response has invalid field types.');
        }

        return $result;
    }

    public function finding(array $result): array
    {
        $this->requireKeys($result, ['status', 'confidence', 'finding', 'extracted_values', 'manual_review_reason', 'warnings', 'evidence']);
        if (FindingStatus::tryFrom((string) $result['status']) === null || ! is_string($result['finding'])
            || ! is_array($result['extracted_values']) || ! is_array($result['warnings']) || ! is_array($result['evidence'])) {
            throw new InvalidAiResponseException('Finding response has invalid field types.');
        }
        $this->confidence($result['confidence']);
        foreach ($result['evidence'] as $evidence) {
            if (! is_array($evidence)) {
                throw new InvalidAiResponseException('Finding evidence must be an object.');
            }
            $this->requireKeys($evidence, ['file_id', 'page_number', 'quote_or_value', 'evidence_type', 'confidence']);
            if (! is_string($evidence['file_id']) || ! is_string($evidence['quote_or_value'])
                || ! in_array($evidence['evidence_type'], ['TEXT', 'IMAGE', 'VALUE', 'DECLARATION'], true)) {
                throw new InvalidAiResponseException('Finding evidence has invalid field values.');
            }
            if ($evidence['confidence'] !== null) {
                $this->confidence($evidence['confidence']);
            }
        }

        return $result;
    }

    private function requireKeys(array $value, array $keys): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $value)) {
                throw new InvalidAiResponseException("AI response is missing required field: {$key}.");
            }
        }
    }

    private function confidence(mixed $value): void
    {
        if (! is_numeric($value) || (float) $value < 0 || (float) $value > 1) {
            throw new InvalidAiResponseException('AI confidence must be between zero and one.');
        }
    }
}
