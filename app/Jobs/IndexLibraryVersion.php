<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LibraryDocumentVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Extracts a library version's embedded PDF text (pdftotext, no OCR) into searchable chunks for AMIRA (ADR-369).
 * A missing tool or unreadable file leaves the version unindexed; it never fails the upload.
 */
final class IndexLibraryVersion implements ShouldQueue
{
    use Queueable;

    public const CHUNK_CHARS = 1000;

    public const MAX_CHUNKS = 2000;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly int $versionId)
    {
        // Own queue on the AMIRA connection, so indexing never delays a PSP's answer (worker: --queue=amira,amira-index).
        $this->onConnection('amira');
        $this->onQueue('amira-index');
    }

    public function handle(): void
    {
        $version = LibraryDocumentVersion::query()->find($this->versionId);
        if ($version === null) {
            return;
        }
        try {
            $process = new Process([(string) config('kaunter.precheck.pdftotext', 'pdftotext'), '-layout', '-enc', 'UTF-8',
                Storage::disk('local')->path($version->path), '-']);
            $process->setTimeout(max(60, (int) config('kaunter.precheck.process_timeout', 60)));
            $process->run();
            if (! $process->isSuccessful()) {
                Log::warning('Library version not indexed: pdftotext failed.', ['version_id' => $version->id, 'exit' => $process->getExitCode()]);

                return;
            }
            $text = $process->getOutput();
        } catch (Throwable $exception) {
            Log::warning('Library version not indexed: text tool unavailable.', ['version_id' => $version->id, 'error' => $exception::class]);

            return;
        }

        $rows = [];
        foreach (explode("\f", mb_scrub($text, 'UTF-8')) as $pageIndex => $page) {
            foreach ($this->chunks($page) as $body) {
                if (count($rows) >= self::MAX_CHUNKS) {
                    break 2;
                }
                $rows[] = ['version_id' => $version->id, 'document_id' => $version->document_id, 'chunk_no' => count($rows) + 1,
                    'page' => $pageIndex + 1, 'body' => $body];
            }
        }
        DB::transaction(function () use ($version, $rows): void {
            DB::table('core.library_chunks')->where('version_id', $version->id)->delete();
            foreach (array_chunk($rows, 200) as $batch) {
                DB::table('core.library_chunks')->insert($batch);
            }
        });
    }

    /** Paragraph-aware chunks of about CHUNK_CHARS characters, whitespace collapsed. */
    private function chunks(string $page): array
    {
        $chunks = [];
        $current = '';
        foreach (preg_split('/\n\s*\n/u', $page) ?: [] as $paragraph) {
            $paragraph = trim((string) preg_replace('/\s+/u', ' ', $paragraph));
            if ($paragraph === '') {
                continue;
            }
            foreach (mb_str_split($paragraph, self::CHUNK_CHARS) as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > self::CHUNK_CHARS) {
                    $chunks[] = $current;
                    $current = '';
                }
                $current = $current === '' ? $piece : $current.' '.$piece;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
