<?php

declare(strict_types=1);

namespace App\Services\Precheck;

final readonly class ExtractedText
{
    /** Always valid UTF-8: pdftotext builds without UTF-8 output give Windows-1252 bytes, which would blank every /u regex. */
    public string $text;

    public function __construct(
        string $text,
        public bool $ocrUsed = false,
        public bool $ocrUnavailable = false,
        public bool $corrupt = false,
    ) {
        $this->text = mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
}
