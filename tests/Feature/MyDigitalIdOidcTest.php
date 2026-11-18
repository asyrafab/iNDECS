<?php

namespace Tests\Feature;

use App\Services\Identity\MyDigitalIdOidcClient;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use phpseclib4\Crypt\RSA;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MyDigitalIdOidcTest extends TestCase
{
    use RefreshDatabase;

    private array $transaction = ['state' => 'synthetic-state', 'nonce' => 'synthetic-nonce', 'code_verifier' => 'synthetic-verifier'];

    private function fixture(string $fault = ''): void
    {
        Http::preventStrayRequests();
        config(['mydigitalid.enabled' => true, 'mydigitalid.network_enabled' => true, 'mydigitalid.sandbox_confirmed' => true,
            'mydigitalid.issuer' => 'https://oidc.example.test', 'mydigitalid.client_id' => 'synthetic-client',
            'mydigitalid.client_secret' => 'synthetic-secret', 'mydigitalid.redirect_uri' => 'https://portal.example.test/portal/mydigitalid/callback']);
        $private = RSA::createKey(2048)->toString('PKCS8');
        $details = openssl_pkey_get_details(openssl_pkey_get_private($private));
        $encode = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $jwk = ['kty' => 'RSA', 'kid' => 'synthetic-key', 'use' => 'sig', 'alg' => 'RS256',
            'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e'])];
        $claims = ['iss' => config('mydigitalid.issuer'), 'aud' => 'synthetic-client', 'sub' => 'synthetic-person',
            'iat' => time(), 'exp' => time() + 300, 'auth_time' => time(), 'nonce' => $this->transaction['nonce']];
        match ($fault) {
            'issuer' => $claims['iss'] = 'https://wrong.example.test',
            'audience' => $claims['aud'] = 'wrong-client',
            'expiry' => $claims['exp'] = time() - 30,
            'nonce' => $claims['nonce'] = 'wrong-nonce',
            'freshness' => $claims['auth_time'] = time() - 600,
            'missing_exp' => $claims = array_diff_key($claims, ['exp' => true]),
            'missing_nonce' => $claims = array_diff_key($claims, ['nonce' => true]),
            'missing_sub' => $claims = array_diff_key($claims, ['sub' => true]),
            'azp' => $claims = array_replace($claims, ['aud' => ['synthetic-client', 'other-client'], 'azp' => 'wrong-client']),
            default => null,
        };
        $token = JWT::encode($claims, $private, 'RS256', 'synthetic-key');
        if ($fault === 'algorithm') {
            $token = JWT::encode($claims, str_repeat('synthetic-hmac-key', 4), 'HS256', 'synthetic-key');
        }
        if ($fault === 'signature') {
            $parts = explode('.', $token);
            $parts[2] = $encode(random_bytes(256));
            $token = implode('.', $parts);
        }
        $metadata = ['issuer' => config('mydigitalid.issuer'), 'authorization_endpoint' => 'https://oidc.example.test/auth',
            'token_endpoint' => 'https://oidc.example.test/token', 'userinfo_endpoint' => 'https://oidc.example.test/userinfo',
            'jwks_uri' => 'https://oidc.example.test/jwks', 'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'], 'id_token_signing_alg_values_supported' => ['RS256'],
            'code_challenge_methods_supported' => ['S256']];
        if ($fault === 'endpoint') {
            $metadata['token_endpoint'] = 'https://untrusted.example.test/token';
        }
        Http::fake([
            'https://oidc.example.test/.well-known/openid-configuration' => Http::response($metadata),
            'https://oidc.example.test/jwks' => Http::response(['keys' => [$jwk]]),
            'https://oidc.example.test/token' => Http::response(['id_token' => $token, 'access_token' => 'synthetic-access', 'token_type' => 'Bearer']),
            'https://oidc.example.test/userinfo' => Http::response(['sub' => $fault === 'userinfo' ? 'wrong-person' : 'synthetic-person', 'name' => 'Synthetic Official Name']),
        ]);
    }

    public function test_valid_signed_token_and_pkce_use_library_through_fake_http(): void
    {
        $this->fixture();
        $client = app(MyDigitalIdOidcClient::class);
        $url = $client->authorizationUrl($this->transaction);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('nonce=synthetic-nonce', $url);
        $identity = $client->complete('synthetic-code', $this->transaction);
        $this->assertSame('synthetic-person', $identity->subject);
        Http::assertSent(fn ($r) => $r->url() === 'https://oidc.example.test/token' && str_contains($r->body(), 'code_verifier=synthetic-verifier'));
    }

    public static function faults(): array
    {
        return array_map(fn ($v) => [$v], ['issuer', 'audience', 'expiry', 'nonce', 'freshness', 'signature', 'algorithm', 'missing_exp', 'missing_nonce', 'missing_sub', 'azp', 'userinfo', 'endpoint']);
    }

    #[DataProvider('faults')]
    public function test_invalid_provider_evidence_is_rejected(string $fault): void
    {
        $this->fixture($fault);
        $this->expectException(\Throwable::class);
        app(MyDigitalIdOidcClient::class)->complete('synthetic-code', $this->transaction);
    }

    public function test_transport_is_disabled_by_default_without_network_calls(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        config(['mydigitalid.enabled' => true]);
        try {
            app(MyDigitalIdOidcClient::class)->authorizationUrl($this->transaction);
            $this->fail('Unconfigured transport accepted');
        } catch (\RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_rotated_signing_key_is_retrieved_on_the_next_transaction(): void
    {
        $this->fixture();
        $client = app(MyDigitalIdOidcClient::class);
        $this->assertSame('synthetic-person', $client->complete('first-synthetic-code', $this->transaction)->subject);
        Http::swap(new Factory);
        $this->fixture(); // A freshly generated RSA key, deliberately retaining the same kid.
        $this->assertSame('synthetic-person', $client->complete('second-synthetic-code', $this->transaction)->subject);
    }
}
