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

    public function test_declaration_using_di_atas_gives_no_address(): void
    {
        $this->assertSame([], $this->read('MAKLUMAT YANG DIBERIKAN DI ATAS ADALAH BENAR. TANDATANGAN PEMOHON'));
    }

    public function test_title_without_a_real_ending_gives_nothing(): void
    {
        $this->assertSame([], $this->read('PERMOHONAN KELULUSAN PELAN BAGI CADANGAN PEMBANGUNAN LAMPU JALAN SKALA 1:500 NOTA SEMUA UKURAN DALAM MM LEGEND'));
    }

    public function test_permohonan_far_from_bagi_gives_nothing(): void
    {
        $this->assertSame([], $this->read('PERMOHONAN '.str_repeat('SYARAT AM ', 40).'BAGI CADANGAN MEMBINA JALAN DI ATAS LOT 5. UNTUK TETUAN X'));
    }

    public function test_address_ends_at_untuk_without_a_space(): void
    {
        $this->assertSame('LOT 1', $this->read('PERMOHONAN BAGI CADANGAN MEMBINA JALAN DI ATAS LOT 1.UNTUK TETUAN Y')['site_address'] ?? null);
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
        $found = $this->read('PERMOHONAN BAGI CADANGAN '.str_repeat('KEDAI ', 100).'DI ATAS LOT 1. UNTUK TETUAN X');

        $this->assertSame(500, mb_strlen($found['title']));
        $this->assertSame('LOT 1', $found['site_address']);
    }

    /** MBSP "Borang pengemukaan": the address is followed by the next form label, not by UNTUK. */
    public function test_submission_form_address_ends_at_the_next_label(): void
    {
        $found = $this->read('BORANG PENGEMUKAAN PELAN LAMPU JALAN UNTUK PEMBANGUNAN TAJUK CADANGAN :Permohonan Kelulusan Pelan Lampu Jalan Bagi Cadangan Pembangunan yang terdiri daripada: Fasa 1: i) 14 Unit Kedai Pejabat Berkembar 2 Tingkat di atas Sebahagian lot 22067 (PT6182) Lebuhraya Bandar Cassia, Mukim 13, Seberang Perai Selatan SEKSYEN/MUKIM : Mukim 13 TARIKH : 17th December 2025 - Sila tandakan (V) bagi kotak yang berkaitan. 1. | Empat (4) set pelan untuk kelulusan oleh MBSP');

        $this->assertSame('CADANGAN PEMBANGUNAN YANG TERDIRI DARIPADA: FASA 1: I) 14 UNIT KEDAI PEJABAT BERKEMBAR 2 TINGKAT', $found['title'] ?? null);
        $this->assertSame('SEBAHAGIAN LOT 22067 (PT6182) LEBUHRAYA BANDAR CASSIA, MUKIM 13, SEBERANG PERAI SELATAN', $found['site_address'] ?? null);
    }

    public function test_address_without_an_ending_nearby_is_left_out_but_title_is_kept(): void
    {
        $found = $this->read('PERMOHONAN BAGI CADANGAN MEMBINA JALAN DI ATAS LOT 5 '.str_repeat('SYARAT AM BERKAITAN ', 30).'UNTUK KELULUSAN MBSP');

        $this->assertSame('CADANGAN MEMBINA JALAN', $found['title'] ?? null);
        $this->assertArrayNotHasKey('site_address', $found);
    }
}
