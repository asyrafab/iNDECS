<?php

declare(strict_types=1);

namespace App\Airs\Contracts;

interface AiProvider
{
    public function classifyDocument(array $input): array;

    public function extractApplicationProfile(array $input): array;

    public function evaluateRule(array $input): array;

    public function generateReport(array $input): array;
}
