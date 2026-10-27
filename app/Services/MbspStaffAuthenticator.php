<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\StaffAuthenticator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

final class MbspStaffAuthenticator implements StaffAuthenticator
{
    public function authenticate(string $staffNumber, #[\SensitiveParameter] string $password): ?array
    {
        $config = config('services.mbsp_staff');
        foreach (['url', 'basic_username', 'basic_password', 'api_key', 'api_token'] as $key) {
            if (! is_string($config[$key] ?? null) || trim($config[$key]) === '') {
                throw new RuntimeException('Perkhidmatan log masuk MBSP belum dikonfigurasi.');
            }
        }

        if (parse_url($config['url'], PHP_URL_SCHEME) !== 'https'
            || ! in_array($config['password_mode'], ['md5', 'raw'], true)
            || in_array($config['api_key'], ['nostaff', 'password'], true)) {
            throw new RuntimeException('Konfigurasi log masuk MBSP tidak sah.');
        }

        try {
            $request = Http::asForm()->accept('*/*')
                ->withBasicAuth($config['basic_username'], $config['basic_password'])
                ->withoutRedirecting()->connectTimeout(5)->timeout(15);

            if (is_string($config['ca_bundle'] ?? null) && trim($config['ca_bundle']) !== '') {
                $request = $request->withOptions(['verify' => $config['ca_bundle']]);
            }

            $response = $request->post($config['url'], [
                $config['api_key'] => $config['api_token'],
                'nostaff' => $staffNumber,
                'password' => $config['password_mode'] === 'md5' ? md5($password) : $password,
            ]);

            if (! $response->successful()) {
                Log::warning('MBSP staff login endpoint returned an unsuccessful response.', [
                    'status' => $response->status(),
                ]);

                throw new RuntimeException('Perkhidmatan log masuk MBSP tidak tersedia. Cuba semula kemudian.');
            }

            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ConnectionException $exception) {
            Log::warning('MBSP staff login endpoint could not be reached.', [
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('Perkhidmatan log masuk MBSP tidak tersedia. Cuba semula kemudian.');
        } catch (JsonException) {
            Log::warning('MBSP staff login endpoint returned malformed JSON.');

            throw new RuntimeException('Perkhidmatan log masuk MBSP tidak tersedia. Cuba semula kemudian.');
        }

        if ($payload === null) {
            return null;
        }

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            throw new RuntimeException('Respon log masuk MBSP tidak sah.');
        }

        $data = $payload['data'] ?? null;
        if ($data === null || $data === []) {
            return null;
        }

        $row = is_array($data) ? ($data[0] ?? null) : null;
        if (! is_array($row) || ! is_scalar($row['NOSTAFF'] ?? null)
            || (string) $row['NOSTAFF'] !== $staffNumber) {
            throw new RuntimeException('Respon log masuk MBSP tidak sah.');
        }

        if (($row['FLAGPASSWORD'] ?? null) === 'expire') {
            throw new RuntimeException('Kata laluan MBSP telah tamat tempoh. Sila kemas kini melalui MBSP sebelum log masuk semula.');
        }

        if (($row['FLAGPASSWORD'] ?? null) !== 'valid') {
            return null;
        }

        return ['staff_number' => $staffNumber];
    }
}
