<?php

declare(strict_types=1);

namespace Tests\Unit\Precheck;

use App\Services\Precheck\DocumentSorter;
use App\Services\Precheck\ExtractedText;
use PHPUnit\Framework\TestCase;

/** pdftotext builds without UTF-8 output give Latin-1 bytes; one such byte must never blank a whole file's text. */
final class ExtractedTextTest extends TestCase
{
    public function test_latin1_text_becomes_valid_utf8_and_keeps_its_words(): void
    {
        $text = (new ExtractedText("SYARAT-SYARAT KHUSUS\r\n7. Gambar Tapak Cadangan \xad 4 keping \x96 LJUP"))->text;

        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
        $this->assertNotNull(preg_replace('/\s+/u', ' ', $text));
        $this->assertStringContainsString('SYARAT-SYARAT KHUSUS', $text);
        $this->assertStringContainsString('–', $text);
    }

    public function test_valid_utf8_is_unchanged(): void
    {
        $this->assertSame('Lebuhraya — Mukim 13', (new ExtractedText('Lebuhraya — Mukim 13'))->text);
    }

    public function test_ljup_file_names_sort_into_ljup1(): void
    {
        $catalogue = json_decode((string) file_get_contents(__DIR__.'/../../../database/catalogues/ljup.v1.json'), true);
        $items = array_map(fn (array $item) => ['code' => $item['code'], 'name' => $item['name'], 'keywords' => $item['precheck_keywords'], 'document_type_id' => 0], $catalogue['items']);
        $sorter = new DocumentSorter;

        foreach (['LAIN_LAIN_LJUP Khusus (1).pdf', 'LAIN_LAIN_LJUP Umum.pdf', 'LAIN_LAIN_LJUP.pdf'] as $name) {
            $top = $sorter->rank($name, 'SYARAT-SYARAT KHUSUS BORANG SEMAKAN', $items)[0];
            $this->assertSame(['borang_ljup1', true], [$top['code'], $top['name_score'] >= 1], $name);
        }
        $top = $sorter->rank('LAIN_LAIN_Cover Letter.pdf', 'PERMOHONAN KELULUSAN PELAN LAMPU JALAN', $items)[0];
        $this->assertSame(['borang_pengemukaan', true], [$top['code'], $top['name_score'] >= 1]);
    }
}
