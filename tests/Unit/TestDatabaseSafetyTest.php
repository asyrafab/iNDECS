<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseSafety;

final class TestDatabaseSafetyTest extends TestCase
{
    public function test_dedicated_postgresql_database_is_allowed(): void
    {
        TestDatabaseSafety::assertSafe('testing', $this->configuration());
        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_targets_are_rejected(string $environment, array $overrides): void
    {
        $this->expectException(RuntimeException::class);
        TestDatabaseSafety::assertSafe($environment, array_replace($this->configuration(), $overrides));
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'normal environment' => ['local', []],
            'application database' => ['testing', ['database' => 'indecs']],
            'other application' => ['testing', ['database' => 'airs']],
            'sqlite' => ['testing', ['driver' => 'sqlite']],
            'url overrides name' => ['testing', ['url' => 'postgresql://localhost/indecs']],
            'url query overrides name' => ['testing', ['url' => 'postgresql://localhost/indecs_test?database=indecs']],
            'write target overrides name' => ['testing', ['write' => ['database' => 'indecs']]],
            'read target overrides name' => ['testing', ['read' => ['database' => 'indecs']]],
            'connector override' => ['testing', ['connect_via_database' => 'indecs']],
            'host DSN override' => ['testing', ['host' => 'localhost;dbname=indecs']],
        ];
    }

    private function configuration(): array
    {
        return ['driver' => 'pgsql', 'database' => 'indecs_test', 'host' => '127.0.0.1', 'port' => '5432'];
    }
}
