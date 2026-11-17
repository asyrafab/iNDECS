<?php

use App\Models\User;
use App\Services\Identity\EmailOtpService;
use App\Services\Identity\ExternalIdentityLinker;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Tests\Support\TestDatabaseSafety;

// Test subprocess only. All input is synthetic; no provider or mail transport is used.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
config($input['config']);
TestDatabaseSafety::assertSafe($app->environment(), $app['db']->connection()->getConfig());
if ($app['db']->selectOne('select current_database() as name')->name !== 'indecs_test') {
    throw new RuntimeException('Test database required.');
}
$app['db']->select('select set_config(?, ?, false)', ['application_name', 'identity-race-'.($input['group'] ?? $input['id'])]);
$request = Request::create('/portal/otp/verify', 'POST');
$request->setLaravelSession($app['session']->driver('array'));
$request->session()->put('identity_browser', 'synthetic-concurrency-browser');
$app->instance('request', $request);
if (($input['action'] ?? 'otp') === 'link') {
    try {
        $app->make(ExternalIdentityLinker::class)->confirm($input['id'], User::findOrFail($input['user']));
        echo 'accepted';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Identity conflict.') {
            throw $exception;
        }
        echo 'rejected';
    }
} else {
    $user = $app->make(EmailOtpService::class)->consume($input['id'], $input['code'], 'email_login');
    echo $user ? 'accepted' : 'rejected';
}
