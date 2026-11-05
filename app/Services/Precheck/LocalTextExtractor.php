<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Contracts\TextExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Local-only text retrieval (ADR-363, contract §3): embedded PDF text first (pdftotext),
 * then bounded Tesseract OCR on rendered pages (Ghostscript) or photos. Every tool runs as
 * an argument list (never a shell string) with a time limit. Any missing tool, timeout or
 * failure becomes ocrUnavailable so the applicant can confirm manually; never a cloud call.
 */
final class LocalTextExtractor implements TextExtractor
{
    public function extract(string $absolutePath, string $mime): ExtractedText
    {
        return $mime === 'application/pdf' ? $this->pdf($absolutePath) : $this->ocrImages([$absolutePath]);
    }

    private function pdf(string $path): ExtractedText
    {
        $pages = max(1, (int) config('kaunter.precheck.ocr_max_pages', 10));
        try {
            $process = $this->run([config('kaunter.precheck.pdftotext'), '-layout', '-l', (string) $pages, $path, '-']);
        } catch (Throwable) {
            $process = null;
        }
        if ($process !== null && ! $process->isSuccessful()) {
            // pdftotext ran but could not parse the file: damaged or not really a PDF.
            return new ExtractedText('', corrupt: true);
        }
        $embedded = $process?->getOutput() ?? '';
        if (mb_strlen(trim($embedded)) >= (int) config('kaunter.precheck.min_text_chars', 30)) {
            return new ExtractedText($embedded);
        }

        $dir = storage_path('app/precheck-tmp/'.Str::ulid());
        File::ensureDirectoryExists($dir);
        try {
            $render = $this->run([config('kaunter.precheck.ghostscript'), '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
                '-sDEVICE=png16m', '-r200', '-dFirstPage=1', '-dLastPage='.$pages, '-sOutputFile='.$dir.'/page-%03d.png', $path]);
            $images = glob($dir.'/page-*.png') ?: [];
            if (! $render->isSuccessful() || $images === []) {
                return new ExtractedText($embedded, ocrUsed: true, ocrUnavailable: true);
            }
            $ocr = $this->ocrImages(array_slice($images, 0, $pages));

            return new ExtractedText(trim($embedded."\n".$ocr->text), ocrUsed: true, ocrUnavailable: $ocr->ocrUnavailable);
        } catch (Throwable) {
            return new ExtractedText($embedded, ocrUsed: true, ocrUnavailable: true);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /** @param list<string> $images */
    private function ocrImages(array $images): ExtractedText
    {
        $text = '';
        foreach ($images as $image) {
            try {
                $process = $this->run([config('kaunter.precheck.tesseract'), $image, '-', '-l', (string) config('kaunter.precheck.tesseract_langs', 'msa+eng')]);
            } catch (Throwable) {
                return new ExtractedText($text, ocrUsed: true, ocrUnavailable: true);
            }
            if (! $process->isSuccessful()) {
                return new ExtractedText($text, ocrUsed: true, ocrUnavailable: true);
            }
            $text .= $process->getOutput()."\n";
        }

        return new ExtractedText($text, ocrUsed: true);
    }

    /** @param list<string> $command */
    private function run(array $command): Process
    {
        $process = new Process($command, null, null, null, (float) config('kaunter.precheck.process_timeout', 60));
        $process->run();

        return $process;
    }
}
