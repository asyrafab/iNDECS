<?php

declare(strict_types=1);

namespace App\Airs\Contracts;

interface DocumentProcessor
{
    public function process(string $filePath, string $outputDirectory): array;
}
