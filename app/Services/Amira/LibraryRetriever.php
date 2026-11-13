<?php

declare(strict_types=1);

namespace App\Services\Amira;

use Illuminate\Support\Facades\DB;

/**
 * Finds public library passages for an AMIRA answer (ADR-369): active, public documents on their current version,
 * optionally only those linked to the draft's application type. Full-text search, any matching word, ranked.
 */
final class LibraryRetriever
{
    private const MAX_WORDS = 12;

    /** @return list<array{id: string, library_document_id: string, version: int, title: string, excerpt: string}> */
    public function search(string $question, ?string $applicationTypeId, int $limit): array
    {
        $words = $this->words($question);
        if ($words === [] || $limit < 1) {
            return [];
        }
        $typeFilter = $applicationTypeId !== null
            ? 'AND EXISTS (SELECT 1 FROM core.library_document_application_types t WHERE t.document_id = d.id AND t.application_type_id = ?)'
            : '';
        $bindings = [implode(' | ', $words)];
        if ($applicationTypeId !== null) {
            $bindings[] = $applicationTypeId;
        }
        $bindings[] = $limit;
        $rows = DB::select(<<<SQL
            SELECT c.document_id, c.body, d.title, d.current_version, ts_rank(c.tsv, q) AS rank
            FROM core.library_chunks c
            JOIN core.library_document_versions v ON v.id = c.version_id
            JOIN core.library_documents d ON d.id = c.document_id
            CROSS JOIN to_tsquery('simple', ?) q
            WHERE c.tsv @@ q AND d.status = 'active' AND d.visibility = 'public' AND v.version = d.current_version {$typeFilter}
            ORDER BY rank DESC, c.id
            LIMIT ?
            SQL, $bindings);

        $max = (int) config('amira.excerpt_chars', 1200);

        return array_values(array_map(fn (object $row, int $i) => [
            'id' => 'S'.($i + 1), 'library_document_id' => $row->document_id, 'version' => (int) $row->current_version,
            'title' => mb_substr($row->title, 0, 255), 'excerpt' => mb_substr(trim($row->body), 0, $max),
        ], $rows, array_keys($rows)));
    }

    /** @return list<string> */
    private function words(string $question): array
    {
        $stopwords = array_flip(config('amira.stopwords', []));
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_filter($words,
            fn (string $word) => mb_strlen($word) >= 3 && ! isset($stopwords[$word])))), 0, self::MAX_WORDS);
    }
}
