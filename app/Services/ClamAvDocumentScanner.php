<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DocumentScanner;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ClamAvDocumentScanner implements DocumentScanner
{
    public function assertClean(string $path): void
    {
        try {
            $command = [(string) config('indecs.document_scanner_binary', 'clamscan'), '--no-summary'];
            if (filled(config('indecs.document_scanner_database'))) {
                $command[] = '--database='.config('indecs.document_scanner_database');
            }
            if (filled(config('indecs.document_scanner_certificates'))) {
                $command[] = '--cvdcertsdir='.config('indecs.document_scanner_certificates');
            }
            $command[] = $path;
            $process = Process::timeout(120)->run($command);
        } catch (Throwable) {
            throw ValidationException::withMessages(['document' => 'Pengimbas keselamatan tidak tersedia. Dokumen tidak disimpan; sila cuba semula selepas perkhidmatan dipulihkan.']);
        }
        if ($process->exitCode() !== 0) {
            throw ValidationException::withMessages(['document' => $process->exitCode() === 1
                ? 'Dokumen ditolak oleh imbasan keselamatan.'
                : 'Imbasan keselamatan tidak dapat diselesaikan. Dokumen tidak disimpan.']);
        }
    }
}
