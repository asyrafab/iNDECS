<?php

declare(strict_types=1);

namespace App\Contracts;

interface DocumentScanner
{
    /** Return only when a real scan reports clean; throw on detection or unavailable scanning. */
    public function assertClean(string $path): void;
}
