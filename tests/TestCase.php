<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\TestDatabaseSafety;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();

        // Runs before RefreshDatabase traits can invoke migrate:fresh.
        TestDatabaseSafety::assertSafe($app->environment(), $connection->getConfig());

        if ($connection->selectOne('select current_database() as name')->name !== 'indecs_test') {
            throw new RuntimeException('Refusing to refresh a database other than indecs_test.');
        }

        return $app;
    }
}
