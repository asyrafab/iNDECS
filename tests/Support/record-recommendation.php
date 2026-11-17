<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\ApplicationRecommendationService;
use App\Services\Kejuruteraan\LjupRecommendationSource;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\TestDatabaseSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
config(['database.connections.pgsql' => $input['database']]);
TestDatabaseSafety::assertSafe($app->environment(), $app['db']->connection()->getConfig());
if ($app['db']->selectOne('select current_database() as name')->name !== 'indecs_test') {
    throw new RuntimeException('Test database required.');
}
$app['db']->select('select set_config(?, ?, false)', ['application_name', 'recommendation-race-'.$input['application']]);
try {
    app(ApplicationRecommendationService::class)->record(User::findOrFail($input['actor']), $input['application'], app(LjupRecommendationSource::class), $input['data']);
    echo 'saved';
} catch (HttpExceptionInterface $exception) {
    if ($exception->getStatusCode() !== 409) {
        throw $exception;
    }
    echo 'conflict';
}
