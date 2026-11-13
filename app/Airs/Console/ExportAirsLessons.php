<?php

declare(strict_types=1);

namespace App\Airs\Console;

use App\Airs\Knowledge\LessonExporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Exports new officer corrections as candidate lessons for MATRIX's evaluation team. */
final class ExportAirsLessons extends Command
{
    protected $signature = 'airs:export-lessons {--since= : Only corrections recorded on or after this date}';

    protected $description = 'Export officer corrections as de-identified MATRIX candidate lessons (JSONL)';

    public function handle(LessonExporter $exporter): int
    {
        $since = $this->option('since') ? CarbonImmutable::parse((string) $this->option('since')) : null;
        $result = $exporter->export($since);
        if ($result['count'] === 0) {
            $this->info('Tiada pelajaran calon baharu.');

            return self::SUCCESS;
        }
        $this->info("{$result['count']} pelajaran calon dieksport ke {$result['file']}");
        $this->warn('Ulasan pegawai ialah teks bebas dan mungkin menyebut nama; fail ini hanya untuk pasukan penilaian MATRIX.');

        return self::SUCCESS;
    }
}
