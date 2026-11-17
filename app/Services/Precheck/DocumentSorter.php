<?php

declare(strict_types=1);

namespace App\Services\Precheck;

/**
 * Ranks checklist items for one dropped file from its name and locally read text (upload-first drafts).
 * Deterministic keyword scoring only — no AI. Score per item: 3 × keywords in the file name
 * + 1 × item-code words (4+ letters) that are file-name words + 2 × keywords in the text
 * + 3 for the item named by the text's heading: the first keyword in its opening that belongs to one item only
 * (the longest when several start at the same place). A form's own title comes before the application subject
 * it quotes, a cover letter's subject comes before the enclosures it lists, and "LAMPU JALAN" inside the subject
 * "PERMOHONAN KELULUSAN PELAN LAMPU JALAN" starts later, so it does not name the lighting plan.
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
     * The item named by the heading's first single-item keyword, or none.
     *
     * @param  list<array{code: string, keywords: list<string>}>  $items
     * @return array<string, true>
     */
    private static function headingCodes(string $heading, array $items): array
    {
        $owners = [];
        foreach ($items as $item) {
            foreach ($item['keywords'] as $keyword) {
                $owners[self::normalise($keyword)][$item['code']] = true;
            }
        }
        $best = null;
        $codes = [];
        foreach ($owners as $keyword => $codesOwning) {
            $position = $keyword === '' || count($codesOwning) > 1 ? false : mb_strpos($heading, (string) $keyword);
            if ($position === false) {
                continue;
            }
            $rank = [$position, -mb_strlen((string) $keyword)];
            if ($best === null || $rank < $best) {
                [$best, $codes] = [$rank, $codesOwning];
            }
        }

        return $codes;
    }

    public static function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[_\-.]+/u', ' ', mb_strtoupper($value))));
    }
}
