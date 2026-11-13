<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Contracts\DocumentProcessor;
use RuntimeException;
use Symfony\Component\Process\Process;

final class PythonDocumentProcessor implements DocumentProcessor
{
    public function process(string $filePath, string $outputDirectory): array
    {
        $process = new Process(
            [(string) config('airs.python.binary'), '-m', 'app.main'],
            (string) config('airs.python_worker'),
        );
        $process->setTimeout((int) config('airs.python.timeout_seconds'));
        $process->setInput(json_encode([
            'file_path' => $filePath,
            'output_dir' => $outputDirectory,
            'render' => true,
            'extract_text' => true,
            'make_regions' => true,
        ], JSON_THROW_ON_ERROR));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Document processing failed. '.$this->safeWorkerMessage($process->getErrorOutput()));
        }

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($result) || ! isset($result['page_count'], $result['pages'], $result['warnings']) || ! is_array($result['pages'])) {
            throw new RuntimeException('Document worker returned an invalid response.');
        }

        return $result;
    }

    private function safeWorkerMessage(string $message): string
    {
        $message = preg_replace('/[\r\n]+/', ' ', trim($message)) ?? '';

        return mb_substr($message, 0, 300);
    }
}
