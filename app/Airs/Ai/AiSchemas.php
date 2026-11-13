<?php

declare(strict_types=1);

namespace App\Airs\Ai;

final class AiSchemas
{
    public static function classification(): array
    {
        return self::object([
            'primary_type' => ['type' => 'string'],
            'secondary_tags' => self::listOf(['type' => 'string']),
            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            'reason' => ['type' => 'string'],
            'evidence_page_numbers' => self::listOf(['type' => 'integer', 'minimum' => 1]),
            'warnings' => self::listOf(['type' => 'string']),
        ]);
    }

    public static function profile(): array
    {
        return self::object([
            'profile' => ['type' => 'object', 'additionalProperties' => true],
            'confidence_summary' => ['type' => 'object', 'additionalProperties' => ['type' => 'number']],
            'conflicts' => self::listOf(['type' => 'object', 'additionalProperties' => true]),
        ]);
    }

    public static function finding(): array
    {
        return self::object([
            'status' => ['type' => 'string', 'enum' => [
                'DITUNAIKAN', 'BELUM_DITUNAIKAN', 'TIDAK_BERKAITAN', 'TIDAK_DAPAT_DIKESAN',
                'PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI',
            ]],
            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            'finding' => ['type' => 'string'],
            'extracted_values' => ['type' => 'object', 'additionalProperties' => true],
            'manual_review_reason' => ['type' => ['string', 'null']],
            'warnings' => self::listOf(['type' => 'string']),
            'evidence' => self::listOf(self::object([
                'file_id' => ['type' => 'string'],
                'page_number' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'quote_or_value' => ['type' => 'string'],
                'evidence_type' => ['type' => 'string', 'enum' => ['TEXT', 'IMAGE', 'VALUE', 'DECLARATION']],
                'confidence' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 1],
            ])),
        ]);
    }

    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private static function listOf(array $items): array
    {
        return ['type' => 'array', 'items' => $items];
    }
}
