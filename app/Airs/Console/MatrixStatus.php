<?php

namespace App\Airs\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class MatrixStatus extends Command
{
    protected $signature = 'matrix:status';
    protected $description = 'Check the local MATRIX adapter and native Claude Enterprise sign-in without inference';
    public function handle(): int
    {
        $process = new Process([(string) config('airs.matrix.node'), (string) config('airs.matrix.worker'), 'doctor'], base_path());
        $process->setTimeout(30); $process->run();
        $body = json_decode($process->getOutput(), true);
        $this->line(json_encode(['enabled' => config('airs.matrix.enabled'), 'provider' => config('airs.provider'), 'model' => config('airs.matrix.model'),
            'runtime' => $body ?? ['ready' => false], 'benchmark' => 'NOT_RUN'], JSON_PRETTY_PRINT));
        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
