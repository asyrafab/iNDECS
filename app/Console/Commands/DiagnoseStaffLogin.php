<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class DiagnoseStaffLogin extends Command
{
    protected $signature = 'staff:diagnose-login';

    protected $description = 'Check MBSP staff login configuration and endpoint reachability without sending staff credentials';

    public function handle(): int
    {
        $driver = config('indecs.staff_auth_driver');
        $this->line("Staff auth driver: {$driver}");

        if ($driver === 'local') {
            $this->warn('Local driver is active. MBSP API login is not being used by this environment.');

            return self::SUCCESS;
        }

        $config = config('services.mbsp_staff');
        $missing = collect(['url', 'basic_username', 'basic_password', 'api_key', 'api_token'])
            ->filter(fn (string $key): bool => ! is_string($config[$key] ?? null) || trim($config[$key]) === '')
            ->values();

        if ($missing->isNotEmpty()) {
            $this->error('MBSP staff login configuration is incomplete: '.$missing->implode(', '));

            return self::FAILURE;
        }

        if (parse_url($config['url'], PHP_URL_SCHEME) !== 'https') {
            $this->error('MBSP staff login URL must use HTTPS.');

            return self::FAILURE;
        }

        if (! in_array($config['password_mode'], ['md5', 'raw'], true)) {
            $this->error('MBSP staff login password mode must be md5 or raw.');

            return self::FAILURE;
        }

        if (in_array($config['api_key'], ['nostaff', 'password'], true)) {
            $this->error('MBSP staff login API key field conflicts with a credential field.');

            return self::FAILURE;
        }

        if (is_string($config['ca_bundle'] ?? null) && trim($config['ca_bundle']) !== '' && ! is_file($config['ca_bundle'])) {
            $this->error('Configured MBSP staff login CA bundle file does not exist.');

            return self::FAILURE;
        }

        $this->info('Configuration shape is valid.');

        $host = parse_url($config['url'], PHP_URL_HOST);
        $port = (int) (parse_url($config['url'], PHP_URL_PORT) ?: 443);
        if (! is_string($host) || trim($host) === '') {
            $this->error('MBSP staff login URL host is invalid.');

            return self::FAILURE;
        }

        $this->line('Checking TCP reachability without sending API credentials...');
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (! is_resource($socket)) {
            $this->error('Endpoint is not reachable from this machine on the configured HTTPS port.');

            return self::FAILURE;
        }

        fclose($socket);
        $this->info('Endpoint TCP port is reachable.');

        $this->line('Checking HTTPS verification without sending API credentials...');
        try {
            $request = Http::withoutRedirecting()->connectTimeout(5)->timeout(15);
            if (is_string($config['ca_bundle'] ?? null) && trim($config['ca_bundle']) !== '') {
                $request = $request->withOptions(['verify' => $config['ca_bundle']]);
            }

            $response = $request->get($config['url']);
            $this->info("HTTPS request completed with HTTP {$response->status()}.");
        } catch (ConnectionException $exception) {
            $this->error('HTTPS request failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('A live login still requires an authorised linked staff account.');

        return self::SUCCESS;
    }
}
