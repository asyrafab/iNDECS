<?php

declare(strict_types=1);

namespace App\Airs\Review;

final class DeterministicProfileExtractor
{
    /**
     * @param  array{documents?: list<array<string, mixed>>}  $input
     * @return array{profile: array<string, mixed>, confidence_summary: array<string, float>, conflicts: list<array<string, mixed>>}
     */
    public function extract(array $input): array
    {
        $titleCandidates = [];
        $profile = [];
        $confidence = [];
        $allText = '';
        $sourceNames = [];

        foreach ($input['documents'] ?? [] as $document) {
            $filename = (string) ($document['filename'] ?? 'Dokumen');
            $type = (string) ($document['document_type'] ?? 'UNKNOWN');
            $text = $this->documentText($document['pages'] ?? []);
            if ($text === '') {
                continue;
            }

            $allText .= "\n".$text;

            if ($submissionTitle = $this->capture(
                '/PERMOHONAN\s+KELULUSAN\s+PELAN\s+LAMPU\s+JALAN\s+BAGI\s+(CADANGAN\s+PEMBANGUNAN.*?)\s+UNTUK\s*:?\s*(?:TETUAN\s+)?[A-Z0-9]/isu',
                $text,
            )) {
                $titleCandidates[] = ['value' => $submissionTitle, 'priority' => 30];
                $sourceNames[] = $filename;
            }

            if ($tnbTitle = $this->capture(
                '/CADANGAN\s+TIANG\s+KONKRIT\s+TNB\s+DAN\s+LAMPU\s+JALAN\s+BAGI\s+(CADANGAN\s+PEMBANGUNAN.*?)\s+UNTUK\s+(?:TETUAN\s+)?[A-Z0-9]/isu',
                $text,
            )) {
                $titleCandidates[] = ['value' => $tnbTitle, 'priority' => 10];
                $sourceNames[] = $filename;
            }

            if ($type === 'LJUP_GENERAL_FORM' || str_contains(mb_strtolower($filename), 'ljup umum')) {
                $this->extractGeneralForm($text, $profile, $confidence);
                if (isset($profile['project_title'])) {
                    $titleCandidates[] = ['value' => $profile['project_title'], 'priority' => 20];
                }
                $sourceNames[] = $filename;
            }

            if (! isset($profile['developer']) && $developer = $this->capture(
                '/UNTUK\s*:?\s*(?:TETUAN\s+)?([A-Z0-9][A-Z0-9 .&()\'\/-]+?SDN\.?\s*BHD\.?)/iu',
                $text,
            )) {
                $profile['developer'] = $developer;
                $confidence['developer'] = 0.93;
                $sourceNames[] = $filename;
            }

            if ($type === 'TNB_CONFIRMATION' && preg_match('/CADANGAN\s+TIANG\s+KONKRIT\s+TNB/iu', $text) === 1) {
                $profile['lighting_system'] = 'Tiang konkrit TNB';
                $confidence['lighting_system'] = 0.90;
            }
        }

        if ($titleCandidates !== []) {
            usort($titleCandidates, static fn (array $left, array $right): int => [$right['priority'], mb_strlen($right['value'])] <=> [$left['priority'], mb_strlen($left['value'])]
            );
            $profile['project_title'] = $titleCandidates[0]['value'];
            $confidence['project_title'] = 0.96;
        }

        if (isset($profile['project_title']) && preg_match(
            '/\bLOT\s+([A-Z0-9\/-]+)\s*,?\s*MUKIM\s+([A-Z0-9\/-]+)/iu',
            (string) $profile['project_title'],
            $lot,
        ) === 1) {
            $profile['lot_mukim'] = "Lot {$lot[1]}, Mukim {$lot[2]}";
            $confidence['lot_mukim'] = 0.95;
        }

        if (! isset($profile['consultant_identity_no']) && preg_match('/(?<!\d)\d{6}-\d{2}-\d{4}(?!\d)/', $allText, $identity) === 1) {
            $profile['consultant_identity_no'] = $identity[0];
            $confidence['consultant_identity_no'] = 0.92;
        }

        if ($profile !== []) {
            $profile['profile_source'] = 'Teks PDF tersusun: '.implode(', ', array_values(array_unique($sourceNames)));
            $confidence['application_header'] = max($confidence['project_title'] ?? 0.0, $confidence['developer'] ?? 0.0);
        }

        return [
            'profile' => $profile,
            'confidence_summary' => $confidence,
            'conflicts' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    private function documentText(array $pages): string
    {
        $text = implode("\n", array_map(
            static fn (array $page): string => trim((string) ($page['text'] ?? '')."\n".(string) ($page['ocr_text'] ?? '')),
            $pages,
        ));
        $text = str_replace(["\r", "\u{00A0}"], ['', ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, float>  $confidence
     */
    private function extractGeneralForm(string $text, array &$profile, array &$confidence): void
    {
        if ($title = $this->capture('/TAJUK\s+CADANGAN\s*:?\s*(.*?)\s+PEMAJU\s*:/isu', $text)) {
            $profile['project_title'] = $title;
            $confidence['project_title'] = 0.91;
        }

        if (preg_match(
            '/PEMAJU\s*:\s*(.*?)\s+ALAMAT\s*:\s*(.*?)\s+NO\s*TEL\s*:\s*(.*?)\s+FAX\s*:\s*(.*?)\s+E-?MAIL\s*:\s*(.*?)\s+PERUNDING\s*:/isu',
            $text,
            $developer,
        ) === 1) {
            $this->set($profile, $confidence, 'developer', $developer[1], 0.95);
            $this->set($profile, $confidence, 'developer_address', $developer[2], 0.94);
            $this->set($profile, $confidence, 'developer_phone', $developer[3], 0.93);
            $this->set($profile, $confidence, 'developer_email', $developer[5], 0.90);
        }

        if (preg_match(
            '/PERUNDING\s*:\s*(.*?)\s+ALAMAT\s*:\s*(.*?)\s+NO\s*TEL\s*:\s*(.*?)\s+FAX\s*:\s*(.*?)\s+E-?MAIL\s*:\s*(.*?)(?:_{3,}|Nama\s+J\/Perunding)/isu',
            $text,
            $consultant,
        ) === 1) {
            $this->set($profile, $confidence, 'consultant', $consultant[1], 0.95);
            $this->set($profile, $confidence, 'consultant_address', $consultant[2], 0.94);
            $this->set($profile, $confidence, 'consultant_phone', $consultant[3], 0.93);
            $this->set($profile, $confidence, 'consultant_fax', $consultant[4], 0.90);
            $this->set($profile, $confidence, 'consultant_email', $consultant[5], 0.90);
        }

        if (preg_match('/SURAT\s+KAMI\s*:\s*_*\s*([A-Z0-9][A-Z0-9\/.\-]{2,})/iu', $text, $reference) === 1) {
            $this->set($profile, $confidence, 'our_reference', $reference[1], 0.95);
        }

        if (preg_match('/TARIKH\s*:\s*_*\s*(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/iu', $text, $date) === 1) {
            $this->set($profile, $confidence, 'submission_date', $date[1], 0.92);
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, float>  $confidence
     */
    private function set(array &$profile, array &$confidence, string $field, string $rawValue, float $score): void
    {
        $value = $this->clean($rawValue);
        if ($value === null) {
            return;
        }

        $profile[$field] = $value;
        $confidence[$field] = $score;
    }

    private function capture(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $match) === 1 ? $this->clean($match[1]) : null;
    }

    private function clean(string $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/^[_:\s]+/u', '', $value) ?? $value;
        $value = trim($value);
        if ($value === '' || preg_match('/^[-_]+$/', $value) === 1) {
            return null;
        }

        return $value;
    }
}
