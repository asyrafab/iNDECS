<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface RecommendationSource
{
    /** Called within a transaction; authorizes and locks the source evidence and application. */
    public function lock(User $actor, string $applicationId): array;

    public function outcomes(): array;
}
