<?php

namespace App\Airs\Console;

use App\Airs\Models\ReviewVersion;
use App\Airs\Review\MatrixUsageReport;
use Illuminate\Console\Command;

final class MatrixUsage extends Command
{
    protected $signature = 'matrix:usage {review_version}';
    protected $description = 'Export usage-only JSON for an AIRS review version (no document contents)';
    public function handle(MatrixUsageReport $report): int
    {
        $version = ReviewVersion::findOrFail($this->argument('review_version'));
        $this->line(json_encode($report->forVersion($version), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
