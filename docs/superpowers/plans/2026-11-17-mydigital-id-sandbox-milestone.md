# MyDigital ID sandbox authentication milestone implementation plan

Date: 2026-11-17

Status: **Approved for local implementation and tests by the owner on 2026-11-17. No real credentials, provider connections or changes to existing login methods are authorised.**

Accepted local decision: [ADR-391](../../adr/ADR-391-mydigital-id-sandbox-linking-and-email-otp.md).

## Local implementation status

Local code now provides additive identity records, encrypted one-use linking transactions, a Facile OIDC adapter, email challenges, a separate sandbox page, pilot-only session limits and cleanup/containment commands. Existing login UI and methods remain unchanged. New routes and provider transport are default-off. Notifications are forced to the array mailer; real email/provider logout are deferred.

The planned `PublicAuthenticationSession` and `IdentityAudit` responsibilities are implemented in `SandboxIdentitySession` middleware, the controller authentication helper and `SandboxIdentity::audit`, rather than introducing otherwise empty service wrappers. This is a local implementation detail, not an authorisation change. Browser binding uses a random server-side session secret and local account ID, surviving framework session-ID migration while remaining unavailable to another browser. Confirmation requires the original account, email snapshot and fresh proofs.

The task checklists below remain the detailed review checklist; unchecked real-provider items are intentionally deferred. Local validation results and remaining gates are recorded in [the runbook](../../runbooks/mydigitalid-sandbox.md). A successful authorised real sandbox demonstration is still required before reporting provider integration complete.

## Outcome and limits

An allowlisted existing public user explicitly links a MyDigital ID sandbox identity, then signs in through SSO or email OTP to the **same internal user and existing draft**. A verified guest may participate without promotion. Staff identities, business permissions and application ownership remain unchanged.

Deliver shared identity components inside iNDECS first. Leave company groups, verification, runner delegation, application assignments, public registration through SSO, SMS, manual recovery screens, self-service unlink/contact replacement, production cut-over and downstream MBSP clients for later plans. Do not remove legacy public methods in this milestone.

## Inspected baseline

| Existing surface | Relevance |
|---|---|
| `composer.json` | Laravel ^13.8, PHP ^8.3, Socialite ^5.31; sample Laravel 10 code is not a dependency specification |
| `routes/public.php` | `/portal` namespace, CSRF-protected POST logout, Google and magic-link routes |
| `PublicAccessController` | Password login, registration, link requests, account claiming and session invalidation |
| `PublicIdentityService` | Case-insensitive email lookup, guest creation and purpose-bound 15-minute magic-link tokens |
| `GoogleLoginController` | Stateful linking precedent; existing-account email matching does not automatically link Google |
| `EnsurePublicAccess` and `User::isPublicAccount()` | Existing active/type authorisation boundary to preserve |
| `core.public_access_tokens` | Existing magic-link table; do not repurpose it silently for OTP |
| `AuditLog` | Append-only audit model to assess and reuse |
| `tests/Feature/PublicAccessTest.php` | Registration, magic-link, Google and staff exclusion regression coverage |
| `tests/Feature/PublicPortalTest.php` | Draft continuity and authorised public journey coverage |

Verify actual migration, guard, database and audit constraints at implementation start; re-read AGENTS.md and applicable documentation. Use PostgreSQL via the repository's documented environment, never SQLite. This planning task does not connect to a database or run migrations.

## Review and dependency gates

### Gate A Owner policy review

- [x] Review ADR-391 and approve the local sandbox scope, linking sequence, OTP defaults and pilot limits. Real-participant data/retention remains deferred.
- [x] Record the local decision without marking the broader company design implemented or accepting unrelated policy.
- [x] Obtain a separate instruction for local implementation and tests. Real credential use and production release remain unauthorised.

### Gate B Provider and environment confirmation

- [ ] Obtain an explicitly approved sandbox client/environment from the MyDigital ID officer. Confirm whether the MBSP ZIP's realm/client can be used; do not test the included secret to discover its entitlement.
- [ ] Confirm exact issuer, discovery and JWKS, allowed signing algorithms, token endpoint authentication, supported scopes, claim names (`name`/`nama`, `nric`, `sub`) and subject stability.
- [ ] Confirm PKCE S256, nonce round-trip, fresh authentication behaviour, redirect registration, logout semantics, mobile QR/deep-link behaviour and session expectations.
- [ ] Register an actual sandbox HTTPS origin and exact callback/logout URLs. Do not register placeholders. Provider-confirmed localhost exceptions are local-only.
- [ ] Approve test identities, destination allowlist, sandbox mail delivery, minimal personal data and retention, operational contacts and provider terms.
- [ ] Separately identify whether future MBSP brokerage requires a new agreement or per-system clients; this does not block local fake development after authorisation, but no brokerage capability may be claimed.

Gate B is required for real interoperability. Local implementation and fake-provider tests can proceed after implementation authorisation while provider details are pending. A provider incompatibility remains an explicit blocker; do not weaken validation to meet a demo date.

## Proposed design contracts

### Components

New paths below are proposed implementation targets, not files created by this task.

| Target | Responsibility |
|---|---|
| `config/mydigitalid.php` | Default-off flags, sandbox restrictions, trusted issuer/client, scopes, claims and limits; secrets read from server environment |
| `app/Contracts/Identity/MyDigitalIdClient.php` | Begin/complete OIDC transactions and optional confirmed logout; return validated identity, not raw untrusted claims |
| `app/Services/Identity/MyDigitalIdOidcClient.php` | Adapter over selected maintained OIDC implementation; trusted discovery/JWKS and strict validation |
| `app/Services/Identity/ExternalIdentityLinker.php` | Eligibility, proof association, atomic link/contact activation and conflicts |
| `app/Services/Identity/EmailOtpService.php` | Purpose-bound challenges, attempts, limits, consumption and delivery eligibility |
| `app/Services/Identity/PublicAuthenticationSession.php` | Consistent login, method/time metadata, regeneration and pilot expiry enforcement |
| `app/Services/Identity/IdentityAudit.php` | Redacted append-only event contract using existing audit where compatible |
| New auth controllers, middleware and notifications | Thin request handling, sandbox/participant guards, CSRF and protected delivery |

Dependency selection is a bounded task, not permission to install the example's package blindly. Record version, licence, PHP support, signature/claim enforcement, discovery controls, state/nonce/PKCE, key rotation and logout in a short dependency decision. Do not edit `vendor/`.

### Data and concurrency

Use additive migrations referencing the existing `users` key. Proposed records:

- `core.external_identities`: user, provider, environment, exact issuer, subject, official name, optional protected NRIC conflict digest, status and timestamps. Unique provider/issuer/subject; one active provider/environment link per user. Sandbox and production never share a link namespace.
- `core.identity_login_contacts`: user, channel=email, encrypted destination, normalised keyed lookup, contact version, status, verification and revocation timestamps. Unique active lookup; enforce collision checks against existing normalised user emails as well. No automatic backfill to eligible status from `email_verified_at` alone.
- `core.identity_challenges`: opaque ID, user/contact, purpose, versioned keyed digest, expiry, attempts, generation and consumed/invalidated timestamps. Purpose is `mydigitalid_enrolment` or `email_login`; no interchangeable tokens.
- OIDC/link transactions: server-side durable or session-backed records with atomic one-time consumption, intent, user, original session association, encrypted transient proof material, creation/expiry and state/nonce/PKCE. Define locks and expiry explicitly rather than relying on client-hidden fields.
- Audit: map existing `AuditLog` fields and failure semantics. If an additive schema adjustment is needed, document why; do not weaken append-only enforcement.

Use consistent lock ordering for account, link, contact and challenge. Enforce uniqueness in PostgreSQL, catch conflicts safely and commit successful link/contact/audit mutations together. Atomically increment attempts and consume proof; test using independent database connections. Resend counters survive replacement. Contact changes and account/link suspension invalidate eligibility at consumption time.

For queued mail, encrypt any recoverable code payload, redact logs and dead-letter visibility, set short retention and verify challenge generation/expiry immediately before sending. Alternatively use bounded synchronous sandbox delivery with the same eligibility checks. Choose and document one implementation before writing delivery code.

### Proposed route contract

Preserve `/portal` and `portal.*`. New routes carry sandbox/master feature gating; mutations are CSRF-protected POSTs. Final route names are subject to collision review.

| Method and path | Purpose |
|---|---|
| POST `/portal/account/mydigitalid/enrolment` | Active allowlisted local user requests proof to their already verified registered email |
| POST `/portal/account/mydigitalid/enrolment/verify` | Verify purpose-bound mailbox proof; bind it to the local user/session |
| POST `/portal/account/mydigitalid/link` | Start explicit linking after fresh enrolment proof |
| POST `/portal/mydigitalid/login` | Start guest login for an already linked participant |
| GET `/portal/mydigitalid/callback` | Complete an existing OIDC transaction; never create/link an account just because a callback arrived |
| POST `/portal/account/mydigitalid/confirm` | Confirm validated pending identity; atomically activate link and login contact |
| GET `/portal/otp` | Accessible email/code UI; no account identifier leakage |
| POST `/portal/otp/request` | Request eligible login challenge; generic response |
| POST `/portal/otp/verify` | Atomically consume challenge and authenticate |
| POST `/portal/otp/resend` | Replace the current challenge within shared abuse limits |
| Existing POST `/portal/logout` | Always clear local session; optional provider redirect according to confirmed contract |

Callbacks distinguish `login` and `link` by server-side transaction intent. Linking requires the same current local account throughout. If a different user signs in, abandon the pending proof. Link requests, callback consumption, final confirmation and OTP verification are guarded even when accessed directly.

## Implementation tasks after authorisation

### Task 1 Establish contracts and disable-by-default configuration

- [x] Review provider evidence and select the OIDC dependency; add it through Composer with a reproducible lockfile only after approval to implement.
- [x] Add adapter interfaces, validated-result types, synthetic provider fixtures and fake delivery seams.
- [x] Add flags for master enablement, enrolment and login, and an explicit participant allowlist. Production must reject enabling this milestone regardless of a boolean flag.
- [x] Document proposed environment variable names without populated secrets. Use an exact issuer/client callback tuple and trusted-host constraints; never infer environment from the sample hostname.
- [x] Add tests showing disabled flags reject starts and completion, incomplete configuration fails closed, production activation is refused and staff/unenrolled users cannot bypass direct routes.

Exit: local contracts and isolation can be reviewed without a real provider or mail connection.

### Task 2 Add identity records and atomic services

- [x] Write additive migration/model changes; verify `users` foreign keys and PostgreSQL schema/search-path behaviour.
- [x] Implement external link/contact invariants, keyed challenge storage and versioned keys. Separate encryption, code-digest and contact-lookup purposes.
- [x] Preserve user ID, account type, role, department, staff fields, display name, passwords, Google subject and business ownership.
- [x] Implement audit integration and retention cleanup with minimal sensitive material.
- [x] Test competing links, duplicate case-normalised contacts, rollback on audit failure, attempt races, resend races and no destructive down-migration use in rollback instructions.

Exit: durable constraints hold under concurrent operations; no account is provisioned by OTP.

### Task 3 Implement explicit enrolment and account linking

- [x] Require an existing active allowlisted public/verified guest session and previously verified registered email. Send a distinct short-lived enrolment code to that saved destination.
- [x] Verify the enrolment code, start OIDC linking and retain only bounded encrypted transient provider proof until final confirmation.
- [x] Require both local mailbox proof and provider authentication to be within five minutes at confirmation; restart proof if either becomes stale. Confirm provider freshness using supported OIDC controls and validated claims; do not assume a fresh callback proves fresh authentication.
- [x] Show a masked identity summary and explicit confirmation; never overwrite the local identity or auto-link by email/NRIC.
- [x] Atomically activate link/contact plus audit and clear pending proof. Same-user repeat is safe; different-user/subject conflicts fail without revealing the other account.
- [x] Test session swaps, stale email snapshots, user suspension, stale proof, replay, cancellation, concurrent confirmation and an identity linked elsewhere.

Exit: the original internal account owns its newly confirmed external identity and OTP contact, with no changes to existing drafts.

### Task 4 Implement strict sandbox SSO login

- [x] Implement provider adapter and trusted discovery/JWKS with bounded timeouts/cache and rotation handling.
- [x] Validate state before exchange, single-use transaction, PKCE, signature, issuer, audience/authorised party, expiry, nonce and UserInfo subject consistency. Reject unsupported algorithms and missing required claims.
- [x] Resolve only active local links to allowlisted active public accounts. Unlinked identities get neutral guidance, no automatic account creation or candidate search results.
- [x] Regenerate session and set local authentication metadata; safe intended return paths remain within the authorised portal.
- [x] Implement provider errors and master-switch behaviour on callbacks, not just the start action.
- [x] Test valid and forged signed fixtures, bad state/nonce/PKCE, expiry, issuer/audience mismatch, mismatched UserInfo, key rotation, network failure and staff links.

Exit: fake interoperability tests demonstrate strict application handling. Real provider compatibility remains pending Gate B.

### Task 5 Implement email OTP fallback

- [x] Request codes only for eligible linked local accounts using their activated saved contact. Use generic responses, opaque dummy challenges for ineligible requests and consistent delivery scheduling to reduce enumeration.
- [x] Implement five-minute/six-digit defaults, five-attempt cap, 60-second resend interval, rolling contact limits plus explicit IP/global initiation and verification limits. Select numeric IP/global ceilings during Task 1 and document them before pilot activation.
- [x] Prevent resend from resetting verification budgets. Keep browser/purpose/contact generation binding and atomic consumption.
- [x] Implement delivery suppression for expired/replaced challenges and controlled failure without logging codes. Keep fallback independent of live SSO availability.
- [x] Test unknown/unlinked/staff/inactive identities, brute force, concurrent consumption, stale generation, wrong purpose/browser, changed contact, revoked link, send failure and SSO outage.

Exit: a linked participant signs into the same account without contacting MyDigital ID; an ineligible address never creates a user or contact.

### Task 6 Add sandbox UI sessions and logout

Proposed modifications: `routes/public.php`, existing public/account views and auth controllers, `EnsurePublicAccess` or narrowly scoped pilot middleware, existing POST logout, and shared CSS only where necessary. Inspect exact Blade paths at implementation time. Avoid unrelated portal/gallery changes already in progress.

- [x] Show enrolment only to eligible pilot accounts. Provide BM labels: `Pautkan MyDigital ID`, `Log masuk dengan MyDigital ID`, `Log masuk alternatif dengan kod OTP` and `Sahkan pautan`.
- [x] Reuse token-based components, native forms/dialog behaviour and accessible one-field code entry with paste/autofill support. Never style with the sample's Tailwind view or expose protocol/secret details in the user flow.
- [x] Enforce proposed pilot idle/absolute limits without globally changing staff/legacy sessions. Apply no-store and secure-cookie requirements on real HTTPS sandbox.
- [x] Ensure local logout succeeds without provider connectivity, hidden iframe/fetch repetitions or blanket localStorage deletion.
- [ ] Confirm and implement real provider logout only after the provider contract and credential use are separately authorised.
- [x] Reject completing a pending flow after disablement; keep logout usable and document revocation of existing pilot sessions separately.

Exit: feature off preserves current UX and routes; feature on gives selected participants a complete accessible journey.

### Task 7 Regression checks and authorised real sandbox demonstration

- [x] Preserve `PublicAccessTest`, `PublicPortalTest` and `MbspStaffLoginTest` regression coverage. New suites consolidate local linking/login/OTP checks in `SandboxIdentityTest`, protocol validation in `MyDigitalIdOidcTest` and independent-connection races in `IdentityConcurrencyTest`.
- [x] Run the affected suites using the repository's PostgreSQL test setup. Confirm test connection isolation before running any RefreshDatabase suite; never aim tests at operational data.
- [x] Run formatting checks for new PHP files, Composer manifest validation and the full local PostgreSQL regression. Results: 43 focused tests passed; final full suite 840 tests, 838 passed, two skipped, 4,083 assertions. Commands and limits are recorded in the runbook.
- [ ] Complete hosted CI and record review/commit identifiers through the repository's PR process; no hosted result is claimed by local verification.
- [ ] After Gate B and explicit credential-use authorisation, enable only in the controlled sandbox and perform the demonstration below. Use synthetic drafts and approved identities, not production copies.
- [ ] Capture redacted evidence without QR payloads, tokens, OTPs, secrets or full NRICs. No automated tests call the real provider.

## Demonstration and acceptance matrix

| Check | Required outcome |
|---|---|
| Enrol existing verified user | Local account/mailbox and provider proofs confirmed; one durable link/contact |
| SSO sign-in after logout | Original user ID and draft ID; unchanged roles and ownership |
| OTP sign-in after logout | Same account/draft; single-use code; authentication method is email OTP |
| Simulated provider outage | Eligible OTP still works without a provider request; SSO fails safely |
| Unknown or newly created legacy guest outside allowlist | No enrolment, OTP login or new identity from new routes |
| Conflict or duplicate link | No account merge, transfer or information about the other user |
| Staff or inactive account | No public linking/login and no privilege mutation |
| Replay expiry and simultaneous verification | At most one authentication/proof consumption; attempts never reset through resend |
| Local/provider logout failure | Local session always ends; no unrelated cookies/storage erased |
| Feature disabled or production | New starts and pending completions denied; legacy/staff regressions unchanged |
| Rollback and containment | Flags can stop new access; authorised session revocation works without deleting audit/business records |

Milestone completion requires **both** application tests and a real approved sandbox result. If only fake-provider checks pass, report implementation complete pending provider verification, not integrated successfully.

## Rollback and operational handover

- [x] Document flag disablement, pending-transaction invalidation and selective pilot-session revocation. Do not use a schema rollback to delete links/audit as an incident response.
- [ ] Document SMTP/provider failure diagnosis, rate-limit alerts, challenge cleanup, encrypted failed-job retention and secret/key rotation. Digest lookup rotation must preserve contact uniqueness and verification checks through an explicit versioned transition.
- [x] Record sandbox link/contact data as environment-specific; agree purge/retention after the pilot. Production requires fresh trusted linking, not copying sandbox identities.
- [x] Update an auth runbook and ADR index only with the implemented/accepted state at the appropriate time. Existing accepted public access decisions remain in force during the pilot.
- [ ] Present redacted demonstration evidence and remaining production/company dependencies for owner review. Do not turn the pilot into a public cut-over or downstream identity provider.

## Deliverables and approval boundary

After later implementation authorisation, expected deliverables are reviewed code and additive migrations, reproducible dependency decision, PostgreSQL negative/concurrency/regression results, a sandbox runbook and redacted provider demonstration. No date estimate is committed before provider access and dependency review are resolved.

The original documentation task created this plan and ADR-391 only. The subsequent owner instruction authorises the local implementation described above. Dependencies were installed and additive migrations exercised against the guarded test database; no real credentials, operational migration, external email delivery, provider registration, sample execution or real login are authorised or claimed.
