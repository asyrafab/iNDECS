<?php

declare(strict_types=1);

namespace App\Services\Precheck;

/**
 * Ranks checklist items for one dropped file from its name and locally read text (upload-first drafts).
 * Deterministic keyword scoring only — no AI. Score per item: 3 × keywords in the file name
 * + 1 × item-code words (4+ letters) that are file-name words + 2 × keywords in the text.
 */
final class DocumentSorter
{
    /**
     * @param  list<array{code: string, keywords: list<string>}>  $items
     * @return list<array{code: string, score: int}>
     */
    public function rank(string $fileName, string $text, array $items): array
    {
        $name = ' '.self::normalise(pathinfo($fileName, PATHINFO_FILENAME)).' ';
        $tokens = array_flip(array_filter(explode(' ', trim($name))));
        $text = self::normalise($text);
        $ranked = [];
        foreach ($items as $item) {
            $score = 0;
            foreach ($item['keywords'] as $keyword) {
                $keyword = self::normalise($keyword);
                if ($keyword === '') {
                    continue;
                }
                $score += str_contains($name, ' '.$keyword.' ') ? 3 : 0;
                $score += $text !== '' && str_contains($text, $keyword) ? 2 : 0;
            }
            foreach (explode('_', mb_strtoupper($item['code'])) as $word) {
                $score += mb_strlen($word) >= 4 && isset($tokens[$word]) ? 1 : 0;
            }
            $ranked[] = ['code' => $item['code'], 'score' => $score];
        }
        usort($ranked, fn (array $a, array $b) => [$b['score'], $a['code']] <=> [$a['score'], $b['code']]);

        return $ranked;
    }

    public static function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[_\-.]+/u', ' ', mb_strtoupper($value))));
    }
}
