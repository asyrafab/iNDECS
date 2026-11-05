<?php

declare(strict_types=1);

namespace App\Services\Precheck;

final readonly class ExtractedText
{
    public function __construct(
        public string $text,
        public bool $ocrUsed = false,
        public bool $ocrUnavailable = false,
        public bool $corrupt = false,
    ) {}
}
