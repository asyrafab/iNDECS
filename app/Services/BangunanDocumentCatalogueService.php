<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BangunanApplication;
use App\Models\User;

/** Bangunan entry point to the shared department document catalogue (ADR-310/362/363). */
final class BangunanDocumentCatalogueService
{
    public function __construct(private readonly DocumentCatalogueService $catalogues) {}

    /** Pre-check keywords (ADR-363): trimmed, upper-cased, de-duplicated, at least 2 characters; null when none. */
    public static function keywords(?array $keywords): ?array
    {
        return DocumentCatalogueService::keywords($keywords);
    }

    public function publish(string $type, array $data, User $actor): void
    {
        abort_unless(in_array($type, BangunanApplication::TYPES, true), 404);
        $this->catalogues->publish($type, $data, $actor);
    }
}
