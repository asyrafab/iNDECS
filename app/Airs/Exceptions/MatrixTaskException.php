<?php

declare(strict_types=1);

namespace App\Airs\Exceptions;

final class MatrixTaskException extends \RuntimeException
{
    public function __construct(string $code, public readonly array $usageMetadata = [])
    {
        parent::__construct($code);
    }
}
