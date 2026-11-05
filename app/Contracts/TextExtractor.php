<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Precheck\ExtractedText;

/** Local-only text retrieval for the free pre-check (ADR-363). Never a network or AI call. */
interface TextExtractor
{
    public function extract(string $absolutePath, string $mime): ExtractedText;
}
