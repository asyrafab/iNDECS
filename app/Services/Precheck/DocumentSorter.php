<?php

declare(strict_types=1);

namespace App\Services\Precheck;

/**
 * Ranks checklist items for one dropped file from its name and locally read text (upload-first drafts).
 * Deterministic keyword scoring only — no AI. Score per item: 3 × keywords in the file name
 * + 1 × item-code words (4+ letters) that are file-name words + 2 × keywords in the text
 * + 3 for the item whose keyword is the longest one in the text's opening (its heading or subject line).
 * Only the longest heading keyword counts, so "LAMPU JALAN" inside a cover letter's subject
 * "PERMOHONAN KELULUSAN PELAN LAMPU JALAN" does not also name the lighting plan.
 */
final class DocumentSorter
{
    /** Characters of normalised text treated as the document's heading (letterhead plus subject line). */
    public const HEADING = 600;

    /**
     * @param  list<array{code: string, keywords: list<string>}>  $items
     * @return list<array{code: string, score: int, name_score: int}> name_score is the file-name part of score
     */
    public function rank(string $fileName, string $text, array $items): array
    {
        $name = ' '.self::normalise(pathinfo($fileName, PATHINFO_FILENAME)).' ';
        $tokens = array_flip(array_filter(explode(' ', trim($name))));
        $text = self::normalise($text);
        $headingCodes = self::headingCodes(mb_substr($text, 0, self::HEADING), $items);
        $ranked = [];
        foreach ($items as $item) {
            $nameScore = 0;
            $textScore = 0;
            foreach ($item['keywords'] as $keyword) {
                $keyword = self::normalise($keyword);
                if ($keyword === '') {
                    continue;
                }
                $nameScore += str_contains($name, ' '.$keyword.' ') ? 3 : 0;
                $textScore += $text !== '' && str_contains($text, $keyword) ? 2 : 0;
            }
            foreach (explode('_', mb_strtoupper($item['code'])) as $word) {
                $nameScore += mb_strlen($word) >= 4 && isset($tokens[$word]) ? 1 : 0;
            }
            $headingScore = isset($headingCodes[$item['code']]) ? 3 : 0;
            $ranked[] = ['code' => $item['code'], 'score' => $nameScore + $textScore + $headingScore, 'name_score' => $nameScore];
        }
        usort($ranked, fn (array $a, array $b) => [$b['score'], $a['code']] <=> [$a['score'], $b['code']]);

        return $ranked;
    }

    /**
     * Items owning the longest keyword found in the heading (several only on an exact length tie).
     *
     * @param  list<array{code: string, keywords: list<string>}>  $items
     * @return array<string, true>
     */
    private static function headingCodes(string $heading, array $items): array
    {
        $longest = 0;
        $codes = [];
        foreach ($items as $item) {
            foreach ($item['keywords'] as $keyword) {
                $keyword = self::normalise($keyword);
                $length = mb_strlen($keyword);
                if ($keyword === '' || $length < $longest || ! str_contains($heading, $keyword)) {
                    continue;
                }
                if ($length > $longest) {
                    [$longest, $codes] = [$length, []];
                }
                $codes[$item['code']] = true;
            }
        }

        return $codes;
    }

    public static function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[_\-.]+/u', ' ', mb_strtoupper($value))));
    }
}
