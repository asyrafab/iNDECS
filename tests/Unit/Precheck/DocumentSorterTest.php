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
        $ranked = (new DocumentSorter)->rank('Lampu Jalan Rev B.pdf', 'LAPORAN FOTOMETRIK', self::ITEMS);

        // filename keyword LAMPU JALAN (3) + code words LAMPU, JALAN (2) = 5; text FOTOMETRIK (2) = 2.
        $this->assertSame(['code' => 'pelan_lampu_jalan', 'score' => 5], $ranked[0]);
        $this->assertSame(['code' => 'laporan_fotometrik', 'score' => 2], $ranked[1]);
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

        $this->assertSame(['code' => 'geran', 'score' => 2], $ranked[0]);
    }
}
