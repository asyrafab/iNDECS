<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Models\ReviewRule;
use App\Airs\Models\ReviewVersion;

final class EvidenceRetriever
{
    public function forRule(ReviewVersion $version, ReviewRule $rule): array
    {
        $classifications = $version->classifications()->with(['file.pages' => fn ($query) => $query->orderBy('page_number')])
            ->when(($rule->required_evidence_types ?? []) !== [], fn ($query) => $query->whereIn('primary_type', $rule->required_evidence_types))->get();
        $evidence = [];
        foreach ($classifications as $classification) {
            $pages = $classification->file->pages;
            // Preserve the legacy provider's input shape; MATRIX receives each relevant page.
            if ($version->ai_provider !== 'matrix') $pages = collect([$pages->first(fn ($page) => trim((string) $page->embedded_text) !== '') ?? $pages->first()])->filter();
            foreach ($pages as $page) {
                $text = trim((string) $page->embedded_text."\n".(string) $page->ocr_text);
                $image = $classification->primary_type === 'SITE_PHOTOGRAPHS';
                $evidence[] = ['file_id' => $classification->review_file_id, 'page_id' => $page->id, 'page_number' => $page->page_number,
                    'document_type' => $classification->primary_type, 'quote_or_value' => $image ? 'Gambar tapak yang dilampirkan.' : mb_substr($text, 0, 1000),
                    'text' => mb_substr($text, 0, 12000), 'evidence_type' => $image ? 'IMAGE' : 'TEXT', 'confidence' => (float) $classification->confidence,
                    'preview_path' => $page->preview_path, 'high_res_path' => $page->high_res_path];
            }
        }
        return $evidence;
    }
}
