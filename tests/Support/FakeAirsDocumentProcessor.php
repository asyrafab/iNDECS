<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Airs\Contracts\DocumentProcessor;

/** Deterministic stand-in for the Python worker: one text-rich LJUP page per file. */
final class FakeAirsDocumentProcessor implements DocumentProcessor
{
    public function __construct(private readonly string $text = 'Pelan lampu jalan. Jadual: 23 unit 150 W dan 7 unit 180 W.') {}

    public function process(string $filePath, string $outputDirectory): array
    {
        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0777, true);
        }
        $textPath = $outputDirectory.DIRECTORY_SEPARATOR.'page-1.txt';
        $preview = $outputDirectory.DIRECTORY_SEPARATOR.'page-1-preview.jpg';
        $thumbnail = $outputDirectory.DIRECTORY_SEPARATOR.'page-1-thumb.jpg';
        file_put_contents($textPath, $this->text);
        file_put_contents($preview, 'preview');
        file_put_contents($thumbnail, 'thumbnail');

        return [
            'page_count' => 1, 'is_encrypted' => false, 'warnings' => [],
            'pages' => [[
                'page' => 1, 'width' => 842, 'height' => 595, 'rotation' => 0, 'content_type' => 'TEXT_RICH',
                'embedded_text_path' => $textPath, 'thumbnail_path' => $thumbnail, 'preview_path' => $preview,
                'high_res_path' => null, 'page_hash' => hash('sha256', $this->text), 'regions' => [],
            ]],
        ];
    }
}
