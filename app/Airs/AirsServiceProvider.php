<?php

declare(strict_types=1);

namespace App\Airs;

use App\Airs\Ai\FakeAiProvider;
use App\Airs\Ai\MatrixClaudeProvider;
use App\Airs\Ai\OpenAiProvider;
use App\Airs\Contracts\AiProvider;
use App\Airs\Contracts\DocumentProcessor;
use App\Airs\DocumentProcessing\PythonDocumentProcessor;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class AirsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentProcessor::class, PythonDocumentProcessor::class);
        $this->app->singleton(AiProvider::class, function ($app): AiProvider {
            $class = match ((string) config('airs.provider')) {
                'fake' => FakeAiProvider::class,
                'openai' => OpenAiProvider::class,
                'matrix' => MatrixClaudeProvider::class,
                default => throw new RuntimeException('Unsupported AIRS_AI_PROVIDER configuration.'),
            };

            return $app->make($class);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([Console\MatrixStatus::class, Console\MatrixUsage::class, Console\RecoverAirsChecks::class, Console\ExportAirsLessons::class]);
        }
    }
}
