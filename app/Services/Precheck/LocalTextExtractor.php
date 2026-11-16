<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Contracts\TextExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Local-only text retrieval (ADR-363, contract §3): embedded PDF text first (pdftotext),
 * then bounded Tesseract OCR on rendered pages (Ghostscript) or photos. Every tool runs as
 * an argument list (never a shell string) within one total time budget that is shorter than
 * the job timeout. A missing tool, timeout or failure becomes ocrUnavailable so the applicant
 * can confirm manually; only a PDF that pdftotext itself rejects is "corrupt". Never a cloud call.
 */
final class LocalTextExtractor implements TextExtractor
{
    private float $deadline = 0.0;

    public function extract(string $absolutePath, string $mime): ExtractedText
    {
        $this->deadline = microtime(true) + max(0, (int) config('kaunter.precheck.total_budget', 240));

        return $mime === 'application/pdf' ? $this->pdf($absolutePath) : $this->ocrImages([$absolutePath]);
    }

    private function pdf(string $path): ExtractedText
    {
        $pages = max(1, (int) config('kaunter.precheck.ocr_max_pages', 10));
        $embedded = '';
        if ($this->installed(config('kaunter.precheck.pdftotext')) && $this->remaining() > 0) {
            $process = $this->run([config('kaunter.precheck.pdftotext'), '-layout', '-enc', 'UTF-8', '-l', (string) $pages, $path, '-']);
            if ($process !== null && $process->isSuccessful()) {
                $embedded = $process->getOutput();
            } elseif ($process !== null && in_array($process->getExitCode(), [1, 2, 3], true)) {
                // poppler: 1 = cannot open, 2 = cannot write, 3 = permissions — the PDF itself is unusable.
                return new ExtractedText('', corrupt: true);
            }
        }
        if (mb_strlen(trim($embedded)) >= (int) config('kaunter.precheck.min_text_chars', 30)) {
            return new ExtractedText($embedded);
        }
        if (! $this->installed(config('kaunter.precheck.ghostscript')) || $this->remaining() <= 0) {
            return new ExtractedText($embedded, ocrUsed: true, ocrUnavailable: true);
        }

        $dir = storage_path('app/precheck-tmp/'.Str::ulid());
        File::ensureDirectoryExists($dir);
        try {
            $render = $this->run([config('kaunter.precheck.ghostscript'), '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
                '-sDEVICE=png16m', '-r200', '-dFirstPage=1', '-dLastPage='.$pages, '-sOutputFile='.$dir.'/page-%03d.png', $path]);
            $images = glob($dir.'/page-*.png') ?: [];
            if ($render === null || ! $render->isSuccessful() || $images === []) {
                return new ExtractedText($embedded, ocrUsed: true, ocrUnavailable: true);
            }
            $ocr = $this->ocrImages(array_slice($images, 0, $pages));

            return new ExtractedText(trim($embedded."\n".$ocr->text), ocrUsed: true, ocrUnavailable: $ocr->ocrUnavailable);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /** @param list<string> $images */
    private function ocrImages(array $images): ExtractedText
    {
        if (! $this->installed(config('kaunter.precheck.tesseract'))) {
            return new ExtractedText('', ocrUsed: true, ocrUnavailable: true);
        }
        $text = '';
        foreach ($images as $image) {
            if ($this->remaining() <= 0) {
                return new ExtractedText($text, ocrUsed: true, ocrUnavailable: true);
            }
            $process = $this->run([config('kaunter.precheck.tesseract'), $image, '-', '-l', (string) config('kaunter.precheck.tesseract_langs', 'msa+eng')]);
            if ($process === null || ! $process->isSuccessful()) {
                return new ExtractedText($text, ocrUsed: true, ocrUnavailable: true);
            }
            $text .= $process->getOutput()."\n";
        }

        return new ExtractedText($text, ocrUsed: true);
    }

    private function installed(string $binary): bool
    {
        return is_file($binary) || (new ExecutableFinder)->find($binary) !== null;
    }

    private function remaining(): float
    {
        return $this->deadline - microtime(true);
    }

    /** @param list<string> $command */
    private function run(array $command): ?Process
    {
        $timeout = min((float) config('kaunter.precheck.process_timeout', 60), $this->remaining());
        if ($timeout <= 0) {
            return null;
        }
        try {
            $process = new Process($command, null, null, null, $timeout);
            $process->run();

            return $process;
        } catch (Throwable) {
            return null;
        }
    }
}
