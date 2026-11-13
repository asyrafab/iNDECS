<?php

declare(strict_types=1);

namespace App\Services\Amira;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Server-side client for the AMIRA backend (ADR-353/369). Never logs the token or message bodies. */
final class AmiraClient
{
    public function ask(array $request): AmiraReply
    {
        try {
            $response = $this->request()->post('/conversation', $request);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('AMIRA is unreachable.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('AMIRA returned HTTP '.$response->status().'.');
        }

        return AmiraReply::fromPayload($response->json('data'));
    }

    private function request(): PendingRequest
    {
        $url = rtrim((string) config('amira.url'), '/');
        $parts = parse_url($url);
        $loopback = is_array($parts) && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        if (! config('amira.enabled') || ! is_array($parts) || ! isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (($parts['scheme'] ?? '') !== 'https' && ! (($parts['scheme'] ?? '') === 'http' && $loopback && app()->environment(['local', 'testing'])))
            || strlen((string) config('amira.token')) < 32) {
            throw new RuntimeException('AMIRA connection is not configured.');
        }

        return Http::baseUrl($url.'/api/v1')->withToken((string) config('amira.token'))->acceptJson()->asJson()
            ->withoutRedirecting()->connectTimeout(5)->timeout((int) config('amira.timeout', 150));
    }
}
