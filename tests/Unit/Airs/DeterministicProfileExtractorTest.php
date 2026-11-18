<?php

declare(strict_types=1);

namespace Tests\Unit\Airs;

use App\Airs\Ai\FakeAiProvider;
use App\Airs\Review\DeterministicProfileExtractor;
use PHPUnit\Framework\TestCase;

final class DeterministicProfileExtractorTest extends TestCase
{
    public function test_extracts_labelled_ljup_profile_and_prefers_full_submission_title(): void
    {
        $extractor = new DeterministicProfileExtractor;
        $result = $extractor->extract(['documents' => [
            [
                'filename' => 'LJUP Umum.pdf',
                'document_type' => 'LJUP_GENERAL_FORM',
                'pages' => [[
                    'text' => <<<'TEXT'
SURAT KAMI :________________PG-0522/MBSP-1
SURAT TUAN:________________ TARIKH :________________
TAJUK CADANGAN CADANGAN PEMBANGUNAN SKIM PERUMAHAN DI ATAS LOT 936, MUKIM 6
PEMAJU : BILLION SITE SDN. BHD.
ALAMAT : NO. 36-G, JALAN KAMPUNG GAJAH, BUTTERWORTH, PENANG.
NO TEL : 04-3103223 FAX : - E-MAIL :
PERUNDING : PERKHIDMATAN PERUNDING UTARA
ALAMAT : 1573, JALAN NANGKA, BUKIT MERTAJAM, PENANG.
NO TEL : 04-5377988 FAX : - E-MAIL :
________________700903-07-5335
Nama J/Perunding No. Kad Pengenalan Tandatangan
TEXT,
                    'ocr_text' => '',
                ]],
            ],
            [
                'filename' => 'Cover Letter.pdf',
                'document_type' => 'SUBMISSION_LETTER',
                'pages' => [[
                    'text' => 'PERMOHONAN KELULUSAN PELAN LAMPU JALAN BAGI CADANGAN PEMBANGUNAN SKIM PERUMAHAN YANG MENGANDUNGI 48 UNIT RUMAH TERES DI ATAS LOT 936, MUKIM 6, BERTAM, KEPALA BATAS, PULAU PINANG. UNTUK: TETUAN BILLION SITE SDN. BHD.',
                    'ocr_text' => '',
                ]],
            ],
            [
                'filename' => 'TNB.pdf',
                'document_type' => 'TNB_CONFIRMATION',
                'pages' => [['text' => 'CADANGAN TIANG KONKRIT TNB DAN LAMPU JALAN', 'ocr_text' => '']],
            ],
        ]]);

        self::assertStringContainsString('48 UNIT RUMAH TERES', $result['profile']['project_title']);
        self::assertSame('BILLION SITE SDN. BHD.', $result['profile']['developer']);
        self::assertSame('Lot 936, Mukim 6', $result['profile']['lot_mukim']);
        self::assertSame('NO. 36-G, JALAN KAMPUNG GAJAH, BUTTERWORTH, PENANG.', $result['profile']['developer_address']);
        self::assertSame('04-3103223', $result['profile']['developer_phone']);
        self::assertSame('PERKHIDMATAN PERUNDING UTARA', $result['profile']['consultant']);
        self::assertSame('PG-0522/MBSP-1', $result['profile']['our_reference']);
        self::assertSame('700903-07-5335', $result['profile']['consultant_identity_no']);
        self::assertSame('Tiang konkrit TNB', $result['profile']['lighting_system']);
    }

    public function test_specific_forms_and_cover_letters_win_over_incidental_terms(): void
    {
        $provider = new FakeAiProvider;

        $special = $provider->classifyDocument([
            'filename' => 'LJUP Khusus.pdf',
            'extension' => 'pdf',
            'page_count' => 1,
            'pages' => [['text' => 'SYARAT-SYARAT KHUSUS dan laporan pengujian fotometrik']],
        ]);
        $cover = $provider->classifyDocument([
            'filename' => 'Cover Letter.pdf',
            'extension' => 'pdf',
            'page_count' => 1,
            'pages' => [['text' => 'Surat permohonan melampirkan Pelan Kebenaran Merancang']],
        ]);
        $checklist = $provider->classifyDocument([
            'filename' => 'LJUP.pdf',
            'extension' => 'pdf',
            'page_count' => 1,
            'pages' => [['text' => 'BORANG PENGEMUKAAN PELAN LAMPU JALAN. Satu salinan resit pembayaran RM250.']],
        ]);

        self::assertSame('LJUP_SPECIAL_FORM', $special['primary_type']);
        self::assertSame('SUBMISSION_LETTER', $cover['primary_type']);
        self::assertSame('LJUP_SUBMISSION_CHECKLIST', $checklist['primary_type']);
    }
}
