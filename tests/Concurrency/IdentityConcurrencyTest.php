<?php

namespace Tests\Concurrency;

use App\Models\User;
use App\Notifications\SandboxIdentityCode;
use App\Services\Identity\EmailOtpService;
use App\Services\Identity\ExternalIdentityLinker;
use App\Services\Identity\SandboxIdentity;
use App\Services\Identity\VerifiedIdentity;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class IdentityConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_database_connections_cannot_consume_one_code_twice(): void
    {
        Notification::fake();
        $user = User::factory()->create(['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null]);
        $configuration = ['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'mydigitalid.enabled' => true,
            'mydigitalid.participants' => [$user->id], 'mydigitalid.issuer' => 'https://identity.example.test'];
        config($configuration);
        $this->app->instance('encrypter', new Encrypter(str_repeat('x', 32), 'AES-256-CBC'));
        $request = Request::create('/portal/otp/request', 'POST');
        $request->setLaravelSession(app('session')->driver('array'));
        $request->session()->put('identity_browser', 'synthetic-concurrency-browser');
        $this->app->instance('request', $request);
        $identity = app(SandboxIdentity::class);
        DB::table('core.external_identities')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'issuer' => config('mydigitalid.issuer'), 'subject' => 'synthetic-person', 'official_name' => Crypt::encryptString('Synthetic')]);
        DB::table('core.identity_login_contacts')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'destination' => Crypt::encryptString($user->email), 'lookup' => $identity->lookup($user->email), 'key_version' => 'v1', 'verified_at' => now()]);
        $id = app(EmailOtpService::class)->issue($user->email, 'email_login');
        $code = Notification::sent($user, SandboxIdentityCode::class)->last()->code;
        $configuration['database.connections.pgsql'] = DB::connection()->getConfig();
        $configuration['cache.default'] = 'array';
        $input = json_encode(['config' => $configuration, 'id' => $id, 'code' => $code], JSON_THROW_ON_ERROR);
        $processes = [];
        DB::beginTransaction();
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/consume-sandbox-code.php')], base_path(), ['APP_ENV' => 'testing'], $input, 30);
            $process->start();
            $processes[] = $process;
        }
        try {
            $waiting = 0;
            for ($i = 0; $i < 50; $i++) {
                $waiting = DB::table('pg_stat_activity')->where('application_name', 'identity-race-'.$id)->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(100000);
                DB::select('select pg_stat_clear_snapshot()');
            }
        } finally {
            DB::commit();
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $results[] = trim($process->getOutput());
        }
        sort($results);
        $this->assertSame(2, $waiting, 'Both independent consumers must contend on the locked account row.');
        $this->assertSame(['accepted', 'rejected'], $results);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'identity.otp_authenticated')->count());
    }

    public function test_two_accounts_cannot_link_the_same_external_identity_concurrently(): void
    {
        $users = User::factory()->count(2)->create(['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null]);
        $configuration = ['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'mydigitalid.enabled' => true,
            'mydigitalid.participants' => $users->modelKeys(), 'mydigitalid.issuer' => 'https://identity.example.test',
            'database.connections.pgsql' => DB::connection()->getConfig(), 'cache.default' => 'array'];
        config($configuration);
        $this->app->instance('encrypter', new Encrypter(str_repeat('x', 32), 'AES-256-CBC'));
        $request = Request::create('/portal/account/mydigitalid/confirm', 'POST');
        $request->setLaravelSession(app('session')->driver('array'));
        $request->session()->put('identity_browser', 'synthetic-concurrency-browser');
        $this->app->instance('request', $request);
        $linker = app(ExternalIdentityLinker::class);
        $group = (string) Str::uuid();
        $inputs = [];
        foreach ($users as $user) {
            $id = $linker->enrolment($user);
            $proof = $linker->transaction($id, $user, 'mailbox')['proof'];
            DB::table('core.identity_transactions')->where('id', $id)->update(['stage' => 'consumed']);
            $linker->pending($id, $proof, new VerifiedIdentity(config('mydigitalid.issuer'), 'contested-subject', 'Synthetic', now()->timestamp));
            $inputs[] = json_encode(['action' => 'link', 'config' => $configuration, 'id' => $id, 'group' => $group, 'user' => $user->id], JSON_THROW_ON_ERROR);
        }
        DB::beginTransaction();
        User::query()->whereIn('id', $users->modelKeys())->orderBy('id')->lockForUpdate()->get();
        $processes = [];
        foreach ($inputs as $input) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/consume-sandbox-code.php')], base_path(), ['APP_ENV' => 'testing'], $input, 30);
            $process->start();
            $processes[] = $process;
        }
        try {
            $waiting = 0;
            for ($i = 0; $i < 50; $i++) {
                $waiting = DB::table('pg_stat_activity')->where('application_name', 'identity-race-'.$group)->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(100000);
                DB::select('select pg_stat_clear_snapshot()');
            }
        } finally {
            DB::commit();
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $results[] = trim($process->getOutput());
        }
        sort($results);
        $this->assertSame(2, $waiting);
        $this->assertSame(['accepted', 'rejected'], $results);
        $this->assertSame(1, DB::table('core.external_identities')->count());
        $this->assertSame(1, DB::table('core.identity_login_contacts')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'identity.linked')->count());
    }
}
