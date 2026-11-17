<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class SandboxOidcHttpClient implements ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $issuer = parse_url((string) config('mydigitalid.issuer'));
        $parts = parse_url($url);
        if (! app(SandboxIdentity::class)->enabled() || ! config('mydigitalid.network_enabled')
            || ! config('mydigitalid.sandbox_confirmed') || ! $issuer || ! $parts
            || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || ($parts['host'] ?? '') !== ($issuer['host'] ?? null)
            || ($parts['port'] ?? 443) !== ($issuer['port'] ?? 443)) {
            throw new RuntimeException('Sandbox provider transport is unavailable.');
        }
        $response = Http::timeout(5)->connectTimeout(3)->withoutRedirecting()
            ->withHeaders($request->getHeaders())->send($request->getMethod(), $url, ['body' => (string) $request->getBody()]);
        if (strlen($response->body()) > 262144 || $response->status() !== 200) {
            throw new RuntimeException('Sandbox provider response rejected.');
        }

        return $response->toPsrResponse();
    }
}
