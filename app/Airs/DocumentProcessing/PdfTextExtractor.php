<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Models\ReviewFile;

final class PdfTextExtractor
{
    /** @return array<int, string> */
    public function extractedPages(ReviewFile $file): array
    {
        return $file->pages()->orderBy('page_number')->pluck('embedded_text', 'page_number')->all();
    }
}
