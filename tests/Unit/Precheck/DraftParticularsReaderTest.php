<?php

declare(strict_types=1);

namespace Tests\Unit\Precheck;

use App\Services\Precheck\DraftParticularsReader;
use PHPUnit\Framework\TestCase;

/** Upload-first part 2: draft details read from the standard MBSP cover-letter pattern. */
final class DraftParticularsReaderTest extends TestCase
{
    /** OCR of the Green Camour cover letter's subject (scan, local Tesseract). */
    private const LETTER = "Tuan, PERMOHONAN KELULUSAN PELAN LAMPU JALAN BAGI CADANGAN PEMBANGUNAN YANG\nTERDIRI DARIPADA: FASA 1: i) 14 UNIT KEDAI PEJABAT BERKEMBAR 2 TINGKAT ii) 2 UNIT KEDAI PEJABAT SESEBUAH 2 TINGKAT iii) 1 UNIT PENCAWANG ELEKTRIK PADAT\nDI ATAS SEBAHAGIAN LOT 22067 (PT6182), LEBUHRAYA BANDAR CASSIA, BANDAR CASSIA BATU\nKAWAN, MUKIM 13, SEBERANG PERAI SELATAN.\nUNTUK TETUAN GREEN CAMOUR PROPERTY SDN. BHD. Per : Permohonan Kelulusan";

    private function read(string $text): array
    {
        return (new DraftParticularsReader)->read($text);
    }

    public function test_reads_title_from_cover_letter_subject(): void
    {
        $this->assertSame('CADANGAN PEMBANGUNAN YANG TERDIRI DARIPADA: FASA 1: I) 14 UNIT KEDAI PEJABAT BERKEMBAR 2 TINGKAT II) 2 UNIT KEDAI PEJABAT SESEBUAH 2 TINGKAT III) 1 UNIT PENCAWANG ELEKTRIK PADAT',
            $this->read(self::LETTER)['title'] ?? null);
    }

    public function test_reads_site_address_after_di_atas(): void
    {
        $this->assertSame('SEBAHAGIAN LOT 22067 (PT6182), LEBUHRAYA BANDAR CASSIA, BANDAR CASSIA BATU KAWAN, MUKIM 13, SEBERANG PERAI SELATAN',
            $this->read(self::LETTER)['site_address'] ?? null);
    }

    public function test_text_without_the_letter_pattern_gives_nothing(): void
    {
        $this->assertSame([], $this->read('LED STREET LIGHT (ECO PRIME SERIES) 150W QUANTITY : 23 UNITS'));
    }

    public function test_per_without_space_also_ends_the_title(): void
    {
        $this->assertSame('CADANGAN MEMBINA JALAN', $this->read('PERMOHONAN KELULUSAN BAGI CADANGAN MEMBINA JALAN Per: Permohonan')['title'] ?? null);
    }

    public function test_lighting_type_only_when_exactly_one_is_named(): void
    {
        $this->assertSame(['KONKRIT'], $this->read('TIANG KONKRIT 10M')['lighting_types'] ?? null);
        $this->assertSame(['SOLAR'], $this->read('LAMPU JALAN SOLAR')['lighting_types'] ?? null);
        $this->assertSame(['BESI'], $this->read('TIANG BESI GALVANI')['lighting_types'] ?? null);
        $this->assertArrayNotHasKey('lighting_types', $this->read('TIANG KONKRIT 10M DAN TIANG BESI 12M'));
        $this->assertArrayNotHasKey('lighting_types', $this->read('TENAGA SURIA'));
    }

    public function test_values_are_capped(): void
    {
        $found = $this->read('PERMOHONAN BAGI CADANGAN '.str_repeat('KEDAI ', 100).'DI ATAS LOT 1 '.str_repeat('JALAN ', 400).'. UNTUK TETUAN X');

        $this->assertSame(500, mb_strlen($found['title']));
        $this->assertSame(2000, mb_strlen($found['site_address']));
    }
}
