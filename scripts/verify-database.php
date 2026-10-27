<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseSafety;

require __DIR__.'/../vendor/autoload.php';

// Explicit test defaults; URL overrides are still checked after Laravel resolves them.
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_PORT' => getenv('DB_PORT') ?: '5434', 'DB_DATABASE' => 'indecs_test',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$connection = $app['db']->connection();
TestDatabaseSafety::assertSafe($app->environment(), $connection->getConfig());

if ($connection->selectOne('select current_database() as name')->name !== 'indecs_test') {
    throw new RuntimeException('Refusing to rebuild a database other than indecs_test.');
}

$extensions = array_column($connection->select('select extname from pg_extension'), 'extname');
if (array_diff(['postgis', 'pg_trgm'], $extensions) !== []) {
    throw new RuntimeException('Test database is missing required PostgreSQL extensions.');
}

foreach ([['migrate:fresh', ['--seed' => true]], ['migrate:rollback', []], ['migrate', ['--seed' => true]]] as [$command, $options]) {
    $exitCode = $kernel->call($command, $options + ['--force' => true]);
    echo $kernel->output();
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

echo "PostgreSQL extensions and migration round-trip verified on indecs_test.\n";
