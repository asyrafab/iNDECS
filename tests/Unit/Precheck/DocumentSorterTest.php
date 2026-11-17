<?php

declare(strict_types=1);

namespace Tests\Unit\Precheck;

use App\Services\Precheck\DocumentSorter;
use PHPUnit\Framework\TestCase;

final class DocumentSorterTest extends TestCase
{
    private const ITEMS = [
        ['code' => 'pelan_lampu_jalan', 'name' => 'Pelan Lampu Jalan', 'keywords' => ['LAMPU JALAN', 'JADUAL LAMPU']],
        ['code' => 'laporan_fotometrik', 'name' => 'Laporan Fotometrik', 'keywords' => ['FOTOMETRIK', 'LUX']],
        ['code' => 'geran', 'name' => 'Geran', 'keywords' => ['NO. LOT']],
    ];

    public function test_file_name_keyword_outranks_text_only_match(): void
    {
        $ranked = (new DocumentSorter)->rank('Lampu Jalan Rev B.pdf', str_repeat('ISI ', 200).'LAPORAN FOTOMETRIK', self::ITEMS);

        // filename keyword LAMPU JALAN (3) + code words LAMPU, JALAN (2) = 5; text FOTOMETRIK (2) = 2.
        $this->assertSame(['code' => 'pelan_lampu_jalan', 'score' => 5, 'name_score' => 5], $ranked[0]);
        $this->assertSame(['code' => 'laporan_fotometrik', 'score' => 2, 'name_score' => 0], $ranked[1]);
    }

    public function test_tie_orders_by_code(): void
    {
        $ranked = (new DocumentSorter)->rank('dokumen.pdf', '', self::ITEMS);

        $this->assertSame(['geran', 'laporan_fotometrik', 'pelan_lampu_jalan'], array_column($ranked, 'code'));
        $this->assertSame([0, 0, 0], array_column($ranked, 'score'));
    }

    public function test_normalises_underscores_and_case(): void
    {
        $ranked = (new DocumentSorter)->rank('laporan_fotometrik_v2.pdf', '', self::ITEMS);

        $this->assertSame('laporan_fotometrik', $ranked[0]['code']);
        $this->assertSame(5, $ranked[0]['score']);
    }

    public function test_keyword_must_match_whole_words_in_file_name(): void
    {
        $ranked = (new DocumentSorter)->rank('deluxe.pdf', '', self::ITEMS);

        $this->assertSame(0, collect($ranked)->firstWhere('code', 'laporan_fotometrik')['score']);
    }

    public function test_keyword_with_punctuation_matches_text(): void
    {
        $ranked = (new DocumentSorter)->rank('x.pdf', "SURAT\nNo.  Lot 4412", self::ITEMS);

        // text NO. LOT (2) + it opens the document, so it is the heading (3).
        $this->assertSame(['code' => 'geran', 'score' => 5, 'name_score' => 0], $ranked[0]);
    }

    private const LETTER_ITEMS = [
        ['code' => 'pelan_lampu_jalan', 'keywords' => ['LAMPU JALAN']],
        ['code' => 'borang_pengemukaan', 'keywords' => ['SURAT PERMOHONAN', 'PERMOHONAN KELULUSAN PELAN LAMPU JALAN']],
        ['code' => 'pelan_kebenaran_merancang', 'keywords' => ['KEBENARAN MERANCANG', 'PELAN BANGUNAN', 'SITE PLAN']],
    ];

    public function test_heading_names_the_document_over_enclosures_listed_in_its_body(): void
    {
        $letter = 'PERUNDING M&E RUJ KAMI : PLA 1 TUAN, PERMOHONAN KELULUSAN PELAN LAMPU JALAN BAGI CADANGAN PEMBANGUNAN '
            .str_repeat('ISI ', 200).'DISERTAKAN: SITE PLAN, SURAT KEBENARAN MERANCANG, PELAN BANGUNAN';

        $ranked = (new DocumentSorter)->rank('SURAT PERMOHONAN (1).pdf', $letter, self::LETTER_ITEMS);

        // name SURAT PERMOHONAN (3) + text subject (2) + heading (3) = 8; enclosures 3 × 2 = 6; LAMPU JALAN text only = 2.
        $this->assertSame(['code' => 'borang_pengemukaan', 'score' => 8, 'name_score' => 3], $ranked[0]);
        $this->assertSame(['code' => 'pelan_kebenaran_merancang', 'score' => 6, 'name_score' => 0], $ranked[1]);
        $this->assertSame(['code' => 'pelan_lampu_jalan', 'score' => 2, 'name_score' => 0], $ranked[2]);
    }

    public function test_keyword_inside_the_heading_keyword_does_not_also_name_the_document(): void
    {
        $ranked = (new DocumentSorter)->rank('x.pdf', 'PERMOHONAN KELULUSAN PELAN LAMPU JALAN', self::LETTER_ITEMS);

        $this->assertSame(['code' => 'borang_pengemukaan', 'score' => 5, 'name_score' => 0], $ranked[0]);
        $this->assertSame(['code' => 'pelan_lampu_jalan', 'score' => 2, 'name_score' => 0], $ranked[1]);
    }

    private const FORM_ITEMS = [
        ['code' => 'borang_ljup1', 'keywords' => ['BORANG SEMAKAN', 'LJUP']],
        ['code' => 'borang_pengemukaan', 'keywords' => ['BORANG PENGEMUKAAN', 'PERMOHONAN KELULUSAN PELAN LAMPU JALAN', 'LJUP']],
    ];

    public function test_heading_is_the_first_keyword_not_a_quoted_subject(): void
    {
        // A checklist form quotes the application subject in its "Tajuk cadangan" field after its own title.
        $ranked = (new DocumentSorter)->rank('x.pdf', 'BORANG SEMAKAN PELAN LAMPU JALAN TAJUK CADANGAN: PERMOHONAN KELULUSAN PELAN LAMPU JALAN', self::FORM_ITEMS);

        $this->assertSame(['code' => 'borang_ljup1', 'score' => 5, 'name_score' => 0], $ranked[0]);
        $this->assertSame(['code' => 'borang_pengemukaan', 'score' => 2, 'name_score' => 0], $ranked[1]);
    }

    public function test_keyword_shared_by_items_cannot_be_the_heading(): void
    {
        $ranked = (new DocumentSorter)->rank('LJUP.pdf', 'LJUP JABATAN KEJURUTERAAN BORANG PENGEMUKAAN PELAN LAMPU JALAN', self::FORM_ITEMS);

        // name LJUP (3) + text LJUP (2) for both; BORANG PENGEMUKAAN text (2) + heading (3).
        $this->assertSame(['code' => 'borang_pengemukaan', 'score' => 10, 'name_score' => 3], $ranked[0]);
        $this->assertSame(['code' => 'borang_ljup1', 'score' => 5, 'name_score' => 3], $ranked[1]);
    }

    public function test_longest_keyword_wins_a_heading_starting_at_the_same_place(): void
    {
        $items = [['code' => 'a', 'keywords' => ['LAMPU']], ['code' => 'b', 'keywords' => ['LAMPU JALAN']]];

        $ranked = (new DocumentSorter)->rank('x.pdf', 'LAMPU JALAN', $items);

        $this->assertSame([['code' => 'b', 'score' => 5, 'name_score' => 0], ['code' => 'a', 'score' => 2, 'name_score' => 0]], $ranked);
    }

    public function test_keyword_beyond_the_opening_is_not_a_heading(): void
    {
        $ranked = (new DocumentSorter)->rank('x.pdf', str_repeat('ISI ', 150).'KEBENARAN MERANCANG', self::LETTER_ITEMS);

        $this->assertSame(['code' => 'pelan_kebenaran_merancang', 'score' => 2, 'name_score' => 0], $ranked[0]);
    }
}
