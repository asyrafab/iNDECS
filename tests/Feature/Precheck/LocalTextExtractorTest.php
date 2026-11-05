<?php

namespace Tests\Feature\Precheck;

use App\Services\Precheck\LocalTextExtractor;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * Runs the real local tools when they are installed (pdftotext, Ghostscript, Tesseract with msa).
 *
 * @group local-tools
 */
class LocalTextExtractorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/precheck-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_embedded_text_pdf_is_read_without_ocr(): void
    {
        $this->requireTool(config('kaunter.precheck.pdftotext'));
        $path = $this->dir.'/geran.pdf';
        file_put_contents($path, $this->textPdf('SURAT HAKMILIK NO LOT 4412 MUKIM 6 SEBERANG PERAI'));
        $result = app(LocalTextExtractor::class)->extract($path, 'application/pdf');
        $this->assertFalse($result->corrupt);
        $this->assertFalse($result->ocrUsed);
        $this->assertStringContainsString('HAKMILIK', $result->text);
    }

    public function test_png_is_ocred(): void
    {
        $this->requireTool(config('kaunter.precheck.tesseract'));
        $this->requireTool(config('kaunter.precheck.ghostscript'));
        // Render a "photo" of a document with Ghostscript (no GD/FreeType dependency).
        $pdf = $this->dir.'/source.pdf';
        file_put_contents($pdf, $this->textPdf('SURAT HAKMILIK NO LOT 4412'));
        $path = $this->dir.'/geran.png';
        (new \Symfony\Component\Process\Process([config('kaunter.precheck.ghostscript'), '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
            '-sDEVICE=png16m', '-r200', '-sOutputFile='.$path, $pdf]))->mustRun();
        $result = app(LocalTextExtractor::class)->extract($path, 'image/png');
        $this->assertTrue($result->ocrUsed);
        $this->assertFalse($result->ocrUnavailable, 'Tesseract should run on this machine.');
        $this->assertStringContainsString('HAKMILIK', mb_strtoupper($result->text));
    }

    public function test_missing_binary_reports_ocr_unavailable(): void
    {
        config(['kaunter.precheck.tesseract' => $this->dir.'/no-such-tesseract']);
        $path = $this->dir.'/x.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='));
        $result = app(LocalTextExtractor::class)->extract($path, 'image/png');
        $this->assertTrue($result->ocrUnavailable);
        $this->assertFalse($result->corrupt);
    }

    public function test_missing_pdftotext_is_not_reported_as_corrupt(): void
    {
        config(['kaunter.precheck.pdftotext' => $this->dir.'/no-such-pdftotext', 'kaunter.precheck.ghostscript' => $this->dir.'/no-such-gs']);
        $path = $this->dir.'/geran.pdf';
        file_put_contents($path, $this->textPdf('SURAT HAKMILIK'));
        $result = app(LocalTextExtractor::class)->extract($path, 'application/pdf');
        $this->assertFalse($result->corrupt, 'A missing tool must never make a valid PDF unreadable.');
        $this->assertTrue($result->ocrUnavailable);
    }

    public function test_exhausted_time_budget_reports_ocr_unavailable_without_running_tools(): void
    {
        config(['kaunter.precheck.total_budget' => 0]);
        $path = $this->dir.'/x.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='));
        $result = app(LocalTextExtractor::class)->extract($path, 'image/png');
        $this->assertTrue($result->ocrUnavailable);
        $this->assertSame('', trim($result->text));
    }

    private function requireTool(string $binary): void
    {
        if (! is_file($binary) && (new ExecutableFinder)->find($binary) === null) {
            $this->markTestSkipped("{$binary} is not installed.");
        }
    }

    /** Minimal single-page PDF with real embedded text (Helvetica). */
    private function textPdf(string $text): string
    {
        $stream = "BT /F1 18 Tf 50 750 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
