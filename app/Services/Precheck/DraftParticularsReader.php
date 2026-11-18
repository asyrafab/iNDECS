<?php

declare(strict_types=1);

namespace App\Services\Precheck;

/**
 * Reads draft details from document text using the standard MBSP cover-letter subject:
 * "PERMOHONAN … BAGI CADANGAN … DI ATAS LOT … UNTUK TETUAN …". Local and deterministic (ADR-363);
 * returns only what it finds, and nothing rather than a guess.
 */
final class DraftParticularsReader
{
    private const TITLE_MAX = 500;

    private const ADDRESS_MAX = 2000;

    /** Real addresses are short (pilots: 70–120 characters); anything longer has lost its ending. */
    private const ADDRESS_SEARCH = 400;

    /** A lighting type is named only as a pole or light type, never as a loose word. */
    private const LIGHTING = [
        'SOLAR' => '/\b(?:LAMPU(?: JALAN)?|LIGHT|TIANG) SOLAR\b|\bSOLAR (?:STREET )?LIGHT/u',
        'KONKRIT' => '/\bTIANG (?:LAMPU )?KONKRIT\b|\bCONCRETE POLE/u',
        'BESI' => '/\bTIANG (?:LAMPU )?(?:BESI|GALVANI\w*)\b|\bGALVANI[SZ]ED (?:STEEL )?POLE/u',
    ];

    /** @return array{title?: string, site_address?: string, lighting_types?: list<string>} */
    public function read(string $text): array
    {
        $text = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $text)));
        $found = [];
        // Only a complete subject counts: PERMOHONAN close before BAGI CADANGAN, and a real ending (never the end of the text).
        // The address is read only from that subject's own "DI ATAS", so everyday "di atas" ("above") elsewhere is ignored.
        $subject = '/\bPERMOHONAN\b.{0,200}?\bBAGI (CADANGAN\b.{1,1500}?)( DI ATAS | UNTUK TETUAN\b| PER ?:)/u';
        if (preg_match($subject, $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            $found['title'] = self::tidy($match[1][0], self::TITLE_MAX);
            // The address follows the subject's own DI ATAS and ends nearby: at UNTUK (letter), PER: (reference)
            // or the next form label such as "SEKSYEN/MUKIM :" (MBSP submission form). No nearby ending, no address.
            $rest = substr($text, $match[2][1] + strlen($match[2][0]));
            if ($match[2][0] === ' DI ATAS '
                && preg_match('/^(.{1,'.self::ADDRESS_SEARCH.'}?)(?:\s*\.?\s*UNTUK\b|\s*PER ?:|\s+[A-Z][A-Z\/]{2,} ?:)/u', $rest, $address) === 1) {
                $found['site_address'] = self::tidy($address[1], self::ADDRESS_MAX);
            }
        }
        $types = array_keys(array_filter(self::LIGHTING, fn (string $pattern) => self::namedAlone($pattern, $text)));
        if (count($types) === 1) {
            $found['lighting_types'] = $types;
        }

        return array_filter($found, fn ($value) => $value !== '' && $value !== []);
    }

    /**
     * True when the type is named at least once outside a list of options. MBSP's standard conditions on every
     * lighting plan read "tiang lampu konkrit atau besi bergalvani …": that lists what is allowed, not what is proposed.
     */
    private static function namedAlone(string $pattern, string $text): bool
    {
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$found, $offset]) {
            $before = substr($text, max(0, $offset - 40), min(40, $offset));
            $after = substr($text, $offset + strlen($found), 40);
            // Only the same sentence counts as context.
            $before = ($dot = strrpos($before, '.')) === false ? $before : substr($before, $dot + 1);
            $after = ($dot = strpos($after, '.')) === false ? $after : substr($after, 0, $dot);
            if (preg_match('/\b(?:ATAU|OR)\b/', $before.' '.$after) !== 1) {
                return true;
            }
        }

        return false;
    }

    private static function tidy(string $value, int $max): string
    {
        return rtrim(mb_substr(trim($value), 0, $max), ' .,;:');
    }
}
