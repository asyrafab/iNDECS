<?php

declare(strict_types=1);

namespace App\Services\Amira;

use RuntimeException;

/** A validated reply from the AMIRA backend's /api/v1/conversation (ADR-369). */
final readonly class AmiraReply
{
    /** @param list<string> $sourceIds */
    public function __construct(
        public string $reply,
        public array $sourceIds,
        public ?array $proposal,
        public bool $mentionsOutcome,
        public string $model,
    ) {}

    public static function fromPayload(mixed $data): self
    {
        if (! is_array($data) || ! is_string($data['reply'] ?? null) || trim($data['reply']) === '' || mb_strlen($data['reply']) > 1500
            || ! is_array($data['source_ids'] ?? null) || ! array_is_list($data['source_ids'])
            || array_filter($data['source_ids'], fn ($id) => ! is_string($id)) !== []
            || ! (is_array($data['proposal'] ?? null) || ($data['proposal'] ?? null) === null)
            || ! is_bool($data['mentions_outcome'] ?? null) || ! is_string($data['model'] ?? null)
            || ($data['official_decision'] ?? null) !== false) {
            throw new RuntimeException('AMIRA reply has an unexpected shape.');
        }

        return new self(trim($data['reply']), $data['source_ids'], $data['proposal'], $data['mentions_outcome'], mb_substr($data['model'], 0, 120));
    }
}
