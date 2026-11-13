<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Models\ReviewFile;

final class PdfPageRenderer
{
    public function previews(ReviewFile $file): array
    {
        return $file->pages()->orderBy('page_number')->get(['page_number', 'thumbnail_path', 'preview_path', 'high_res_path'])->toArray();
    }
}
