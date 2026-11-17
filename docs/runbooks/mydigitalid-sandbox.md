# MyDigital ID local sandbox authentication

Decision: [ADR-391](../adr/ADR-391-mydigital-id-sandbox-linking-and-email-otp.md). Plan: [first milestone](../superpowers/plans/2026-11-17-mydigital-id-sandbox-milestone.md).

## Scope and current safety boundary

This increment adds local account-linking, OIDC adapter validation and email OTP for allowlisted existing verified public/guest accounts. It does not alter password, Google, magic-link registration/recovery or staff authentication. No company memberships or downstream MBSP identity provider are implemented.

All flags are off by default. No `.env` change, operational database migration, real credentials or real provider login was performed for this implementation. New migration execution during verification is confined to guarded PostgreSQL `indecs_test`.

Email notifications explicitly use the in-memory `array` mailer even if the application default is SMTP. There is no real email delivery or queue containing plaintext OTPs. End-to-end tests use Notification fakes; protocol tests exercise the actual adapter/library through intercepted HTTP with generated RSA keys and reserved `example.test` domains. There is no public fake-authentication endpoint.

## Components and routes

- `config/mydigitalid.php`: default-off flags, participant IDs, trusted issuer/client/redirect configuration, time limits and key version.
- `SandboxIdentity`: local/testing/sandbox environment gate, participant/account eligibility, domain-separated keyed digests, append-only successful identity audit events.
- `ExternalIdentityLinker`: durable encrypted one-use transactions; mailbox proof then SSO then explicit confirmation; atomic link/contact activation and conflict handling.
- `EmailOtpService`: purpose/browser/contact-bound codes, database row locks, rolling verification budgets, cooldown and replacement semantics.
- `MyDigitalIdOidcClient`: adapter over Facile OIDC; no bespoke JWT cryptography.
- `SandboxIdentitySession`: pilot-only idle/absolute expiration and revocation. Legacy/staff sessions carry no pilot marker and retain their behaviour.
- `routes/identity-sandbox.php`: separate `/portal/mydigitalid`, `/portal/otp` and protected enrolment actions. Existing login surfaces do not gain or lose buttons in this increment.

The dedicated page is `/portal/mydigitalid`; it is a 404 with the master flag off. It provides account-specific enrolment when already authenticated and SSO/OTP entry when logged out. The default-disabled real adapter cannot complete an interactive provider journey. Use the automated synthetic tests to review the complete local flow until real provider use is separately authorised.

## Configuration contract

These are names and defaults, not instructions to enable a real connection:

| Name | Default / condition |
|---|---|
| `MYDIGITALID_ENABLED` | false; non-production environment required |
| `MYDIGITALID_ENROLMENT_ENABLED` | false |
| `MYDIGITALID_LOGIN_ENABLED` | false |
| `MYDIGITALID_NETWORK_ENABLED` | false; leave false under current authorisation |
| `MYDIGITALID_SANDBOX_CONFIRMED` | false; never infer entitlement from the officer's sample |
| `MYDIGITALID_PARTICIPANTS` | Empty comma-separated internal user IDs |
| `MYDIGITALID_ISSUER` | Empty; exact HTTPS issuer |
| `MYDIGITALID_CLIENT_ID` / `MYDIGITALID_CLIENT_SECRET` | Empty; no supplied credentials copied |
| `MYDIGITALID_REDIRECT_URI` | Empty; exact registered HTTPS callback |
| `MYDIGITALID_NAME_CLAIM` | `name`; provider must confirm `name` versus `nama` |

Outside automated tests the cache must be database or Redis, so rolling budgets and locks are shared across requests/workers. Do not enable the feature with an in-memory or per-process cache. OTP defaults: six digits, five minutes, five wrong attempts per challenge, 60-second resend delay, five sends per contact per 15 minutes, 20 sends per IP and 100 globally per 15 minutes; verification has a 10-attempt rolling contact and 50-attempt IP budget per 15 minutes. Send/verify budgets use cache locks. Per-challenge attempts/consumption also use PostgreSQL locks. The route group caps requests at 60/minute/IP. A denied resend preserves the current challenge for the same browser instead of sending another code.

Pilot sessions expire after 30 minutes idle/eight hours absolute. Master disablement prevents new starts and completions but does not revoke already authenticated pilot sessions. Use containment below when revocation is intended.

## Dependency decision

Selected `facile-it/php-openid-client` 1.0.0 (MIT), with JOSE verification through `facile-it/php-jose-verifier` and `web-token/jwt-library`; versions and distribution references are in `composer.lock`. It supports PHP ^8.2 and the installed PHP 8.5/Laravel 13 stack. No vendor files are modified. The sample's Jumbojett dependency would require a different phpseclib major from the existing installation, and its bundled source provenance was inconsistent.

The adapter uses the library for authorization/token exchange and signature/claim verification; the application adds exact issuer/HTTPS endpoint checks, required nonce/subject/expiry/authentication time and UserInfo subject consistency. Only RS256 and `client_secret_basic` are currently supported. Issuer metadata and JWKS are bounded by five-second request timeout, no redirects, same configured HTTPS origin and 256 KiB response limit. They are fetched per transaction without a persistent key cache, so rotated keys are retrieved on the next operation. Unknown token-controlled URLs are never fetched. Broader provider requirements remain an interoperability gate.

Composer initially installed only the new dependency set without upgrading existing locked packages. Its audit reported nine advisories affecting six existing packages (Laravel framework, phpseclib and Symfony foundation/kernel/mime/routing). No new selected package appeared in that report.

The separate 2026-11-17 dependency follow-up updates only those six packages: Laravel 13.42.0, phpseclib 4.0.3, Symfony http-foundation/http-kernel/routing 8.1.11 and mime 8.1.10. The live Composer lockfile audit and npm audit now report zero known advisories. This resolves the recorded dependency findings; it is not a security-audit clearance. Do not expand the pilot to real participants before unresolved provider and release security gates are reviewed. See the [release review](release-review-2026-11-17.md) for validation and review boundaries.

## Local verification

Use the documented PostgreSQL test setup in [local development](local-development.md). `Tests\TestCase` checks the resolved connection and actual database name before any refresh. Never disable it or run against `indecs`.

```powershell
php vendor/bin/phpunit tests/Feature/SandboxIdentityTest.php tests/Feature/MyDigitalIdOidcTest.php
php vendor/bin/phpunit tests/Concurrency/IdentityConcurrencyTest.php
php vendor/bin/phpunit
```

The concurrency suite runs last in `phpunit.xml`, uses disposable database migrations and starts two independent PHP/PostgreSQL consumers. Parent row locks hold both consumers until PostgreSQL reports both waiting. One test proves exactly one OTP consumption; another proves only one of two accounts can claim a contested external identity, with one contact and audit record. These are actual lock-contention checks, not sequential calls.

Focused local verification on 2026-11-17 passed 43 tests and 256 assertions. Signed-token cases include invalid issuer/audience/authorised party, expiry, nonce, freshness, signature, algorithm, missing claims and mismatched UserInfo, plus key rotation. Account tests cover continuity of the original draft, conflicts, suspension, replay, purpose/browser binding, throttling, audit rollback, containment and legacy-session preservation. Formatting checks for the new PHP files and `composer validate --no-check-publish` passed.

Final full PostgreSQL regression on 2026-11-17: **840 tests, 838 passed, two skipped, 4,083 assertions, zero failures** (`php vendor/bin/phpunit`). This is a local result; hosted CI and real MyDigital ID interoperability are not claimed.

## Cleanup and containment

The following are operational commands available for a later authorised local sandbox, not commands executed against operational records by this task:

```powershell
php artisan identity:sandbox-cleanup
php artisan identity:sandbox-revoke <internal-user-id>
```

Cleanup deletes only expired transient challenges/transactions and retains audit/link records. Revoke locks the selected account, suspends its sandbox link/contact, invalidates its challenges/transactions and writes an audit event. Pilot sessions end at their next request; ordinary legacy sessions and passwords remain unchanged. Both commands refuse other environments. No self-service unlink/relink recovery is exposed. Revocation is intentionally not reversed by a new callback.

Do not drop identity tables or clear the audit log to roll back. Disable flags, invalidate or revoke affected pilot access, and retain evidence according to an approved retention policy. Key rotation needs a versioned migration for lookup uniqueness; changing APP_KEY or key version in place is not a safe rotation procedure.

## Remaining external gates

Provider-confirmed sandbox client, actual claims, PKCE, reauthentication, exact HTTPS redirects, desktop/mobile behaviour and supported logout remain untested. Provider logout is deliberately not guessed; existing POST logout still clears the local session. Real email delivery, participant-data retention and real sandbox use require a later explicit instruction. A passing fake-provider suite is evidence of local application behaviour, not a completed MyDigital ID integration or production readiness.
