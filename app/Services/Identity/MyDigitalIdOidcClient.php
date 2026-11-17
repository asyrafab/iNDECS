<?php

namespace App\Services\Identity;

use App\Contracts\Identity\MyDigitalIdClient;
use Facile\JoseVerifier\JWK\MemoryJwksProvider;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\Issuer;
use Facile\OpenIDClient\Issuer\Metadata\IssuerMetadata;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Service\Builder\UserInfoServiceBuilder;
use Facile\OpenIDClient\Session\AuthSession;
use Nyholm\Psr7\Factory\Psr17Factory;
use RuntimeException;

final class MyDigitalIdOidcClient implements MyDigitalIdClient
{
    public function __construct(private SandboxOidcHttpClient $http) {}

    private function client(): ClientInterface
    {
        foreach (['issuer', 'client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (! is_string(config('mydigitalid.'.$key)) || config('mydigitalid.'.$key) === '') {
                throw new RuntimeException('Incomplete sandbox configuration.');
            }
        }
        $issuer = config('mydigitalid.issuer');
        if (parse_url($issuer, PHP_URL_SCHEME) !== 'https' || parse_url(config('mydigitalid.redirect_uri'), PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('HTTPS is required.');
        }
        $factory = new Psr17Factory;
        // Bounded per-transaction retrieval: no stale key cache or untrusted token jku fetches.
        $metadata = json_decode((string) $this->http->sendRequest($factory->createRequest('GET', rtrim($issuer, '/').'/.well-known/openid-configuration'))->getBody(), true, 32, JSON_THROW_ON_ERROR);
        if (($metadata['issuer'] ?? null) !== $issuer || ! in_array('S256', $metadata['code_challenge_methods_supported'] ?? [], true)) {
            throw new RuntimeException('Provider metadata mismatch.');
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri'] as $key) {
            $url = $metadata[$key] ?? '';
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== parse_url($issuer, PHP_URL_HOST)
                || (parse_url($url, PHP_URL_PORT) ?? 443) !== (parse_url($issuer, PHP_URL_PORT) ?? 443)
                || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                throw new RuntimeException('Untrusted provider endpoint.');
            }
        }
        $keys = json_decode((string) $this->http->sendRequest($factory->createRequest('GET', $metadata['jwks_uri']))->getBody(), true, 32, JSON_THROW_ON_ERROR);

        return (new ClientBuilder)->setHttpClient($this->http)
            ->setIssuer(new Issuer(IssuerMetadata::fromArray($metadata), new MemoryJwksProvider($keys)))
            ->setClientMetadata(ClientMetadata::fromArray(['client_id' => config('mydigitalid.client_id'),
                'client_secret' => config('mydigitalid.client_secret'), 'token_endpoint_auth_method' => config('mydigitalid.auth_method'),
                'redirect_uris' => [config('mydigitalid.redirect_uri')], 'response_types' => ['code'], 'id_token_signed_response_alg' => 'RS256']))->build();
    }

    public function authorizationUrl(array $transaction): string
    {
        return (new AuthorizationServiceBuilder)->setHttpClient($this->http)->build()->getAuthorizationUri($this->client(), [
            'scope' => 'openid', 'state' => $transaction['state'], 'nonce' => $transaction['nonce'],
            'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $transaction['code_verifier'], true)), '+/', '-_'), '='),
            'max_age' => '0', 'prompt' => 'login',
        ]);
    }

    public function complete(string $code, array $transaction): VerifiedIdentity
    {
        $client = $this->client();
        $tokens = (new AuthorizationServiceBuilder)->setHttpClient($this->http)->build()->callback($client,
            ['code' => $code, 'state' => $transaction['state']], config('mydigitalid.redirect_uri'), AuthSession::fromArray($transaction), 300);
        if (! $tokens->getIdToken()) {
            throw new RuntimeException('ID token required.');
        }
        $claims = $tokens->claims();
        $audience = $claims['aud'] ?? null;
        if ((isset($claims['azp']) && $claims['azp'] !== config('mydigitalid.client_id'))
            || (is_array($audience) && count($audience) > 1 && ($claims['azp'] ?? null) !== config('mydigitalid.client_id'))) {
            throw new RuntimeException('Authorised party mismatch.');
        }
        $info = (new UserInfoServiceBuilder)->setHttpClient($this->http)->build()->getUserInfo($client, $tokens);
        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255
            || ($info['sub'] ?? null) !== $claims['sub'] || ($claims['nonce'] ?? null) !== $transaction['nonce']
            || ! is_int($claims['auth_time'] ?? null) || $claims['auth_time'] < time() - 300 || $claims['auth_time'] > time() + 5
            || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= time()) {
            throw new RuntimeException('Identity claims rejected.');
        }
        $name = $info[config('mydigitalid.name_claim')] ?? null;
        if (! is_string($name) || $name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('Official name required.');
        }

        return new VerifiedIdentity($claims['iss'], $claims['sub'], $name, $claims['auth_time']);
    }
}
