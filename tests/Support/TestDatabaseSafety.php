<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\ConfigurationUrlParser;
use RuntimeException;

final class TestDatabaseSafety
{
    public static function assertSafe(string $environment, array $configuration): void
    {
        $resolved = (new ConfigurationUrlParser)->parseConfiguration($configuration);

        if ($environment !== 'testing'
            || ($resolved['driver'] ?? null) !== 'pgsql'
            || ($resolved['database'] ?? null) !== 'indecs_test'
            || array_intersect(['read', 'write', 'options', 'service', 'connect_via_database', 'connect_via_port'], array_keys($resolved)) !== []
            || ! is_string($resolved['host'] ?? null)
            || preg_match('/[;\s]/', $resolved['host'])
            || ! ctype_digit((string) ($resolved['port'] ?? '5432'))) {
            throw new RuntimeException('Database verification requires testing environment and an unsplit PostgreSQL indecs_test connection.');
        }
    }
}
