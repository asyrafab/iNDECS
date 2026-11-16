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
        $subject = '/\bPERMOHONAN\b.{0,200}?\bBAGI (CADANGAN\b.{1,1500}?)(?: DI ATAS (.{1,2500}?)(?:\s*\.?\s*UNTUK\b|\s*PER ?:)| UNTUK TETUAN\b| PER ?:)/u';
        if (preg_match($subject, $text, $match) === 1) {
            $found['title'] = self::tidy($match[1], self::TITLE_MAX);
            if (($match[2] ?? '') !== '') {
                $found['site_address'] = self::tidy($match[2], self::ADDRESS_MAX);
            }
        }
        $types = array_keys(array_filter(self::LIGHTING, fn (string $pattern) => preg_match($pattern, $text) === 1));
        if (count($types) === 1) {
            $found['lighting_types'] = $types;
        }

        return array_filter($found, fn ($value) => $value !== '' && $value !== []);
    }

    private static function tidy(string $value, int $max): string
    {
        return rtrim(mb_substr(trim($value), 0, $max), " .,;:");
    }
}
