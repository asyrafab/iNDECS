<?php

declare(strict_types=1);

namespace App\Airs;

use App\Airs\Contracts\AiProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class AirsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, function ($app): AiProvider {
            $class = match ((string) config('airs.provider')) {
                'fake' => \App\Airs\Ai\FakeAiProvider::class,
                'openai' => \App\Airs\Ai\OpenAiProvider::class,
                'matrix' => \App\Airs\Ai\MatrixClaudeProvider::class,
                default => throw new RuntimeException('Unsupported AIRS_AI_PROVIDER configuration.'),
            };

            return $app->make($class);
        });
    }
}
