# ADR-391: MyDigital ID sandbox login, existing-account linking and email OTP

Date: 2026-11-17

Status: **Accepted for local implementation and tests — owner approval 2026-11-17. Real credentials, provider connections and production activation are not authorised.**

## Context

The owner has selected MyDigital ID as the primary public login, with personal OTP fallback. The wider design supports one person belonging to several companies, including owning or administering several companies, and shared MBSP identity and organisation services hosted in iNDECS first. This ADR proposes only the first, personal-authentication milestone.

Current public access under [ADR-304](ADR-304-public-access-methods.md) includes password, Google and email magic links. The email login path can create a guest account. Existing records use one user registry and account-type checks. Public identity code does not establish a completed OIDC provider for other MBSP systems. The inspected dependency manifest declares Laravel `^13.8`, PHP `^8.3` and Socialite `^5.31`.

The officer-supplied PHP ZIP and the public Laravel sample are references, not production-ready integrations or proof that the supplied client is a sandbox client. Their claim mappings differ (`name` versus `nama`), and logout and validation behaviour need confirmation. Provider instructions are integration evidence; they do not authorise installing, executing or copying their sample into iNDECS.

## Decision

The owner approved this ADR and the implementation plan on 2026-11-17 and instructed local implementation with fake provider responses and email delivery. Existing login methods remain unchanged. The approved first milestone does not authorise any real MyDigital ID credentials or provider demonstration; external prerequisites below remain open.

### 1. Deliver an isolated sandbox milestone

Implement, only after a separate instruction, an end-to-end demonstration for selected **existing, active public or guest accounts with a previously verified email**:

1. Prove control of the existing local account and its registered mailbox.
2. Explicitly link a validated MyDigital ID sandbox identity to that same account.
3. Sign out and sign in through MyDigital ID.
4. Sign out and sign in through a single-use email OTP, including while MyDigital ID is unavailable.
5. Demonstrate unchanged internal user ID, draft ownership, permissions and record history.

An unlinked SSO identity receives guidance to authenticate to an eligible existing account; it does not create an account or expose candidate matches. Staff, inactive users and unenrolled accounts are rejected by new endpoints. Guest status is not automatically promoted.

New public registration, company verification, memberships, runner permissions, application assignments, SMS, manual recovery tooling, self-service unlinking/contact replacement, production deployment and downstream MBSP SSO are outside this milestone. Data and service boundaries must allow these later additions without making a company the login identity.

### 2. Preserve existing identity and business records

Keep the existing `users` registry, guard and public authorisation rules. Add shared identity records in `core`, referencing the existing user key; do not create a parallel person database, change account types or move business ownership. Preserve department fields and history models.

Use a provider-neutral external-identity record keyed uniquely by `(provider, issuer, subject)` and an active identity per user/provider/environment. Sandbox and future production links are different trust relationships; never promote a sandbox subject, proof or verified attribute into production by copying rows.

Official name is separate from the user's existing display name. Keep NRIC only if confirmed necessary: prefer a keyed, versioned digest for conflict detection; retain encrypted raw values only under an approved purpose and retention policy. No matching by name/email/NRIC creates a link or merges users. Identity conflicts stop the process and require later reconciliation.

### 3. Require explicit, fresh account linking

Linking is a distinct intent from login, initiated through a CSRF-protected action by the authenticated, allowlisted public account. Require an explicit purpose-bound email proof to the account's already verified email, followed by MyDigital ID authentication and a final confirmation. The email proof is an **enrolment proof**, not fallback login and not account creation.

Bind local user ID, email snapshot, intent, state, nonce, PKCE verifier and expiry to the same server-side browser transaction. Both proofs must be no older than five minutes at final confirmation. A user/session/email change, expiry, cancellation or conflicting link invalidates the transaction. Pending external proof is not a login credential.

Final confirmation atomically creates the external link, activates the independently verified login contact and writes an audit event. Transactional uniqueness prevents concurrent links and case-insensitive contact collisions, including collisions with existing users. An identity already linked to the same user is idempotent; a link to another user or replacement identity is rejected. Never log an authenticated user into another account as a callback side effect.

### 4. Use complete OIDC validation behind an adapter

Use a confidential server-side Authorization Code client with state, nonce and PKCE `S256`. Select a maintained OIDC implementation compatible with the actual Laravel/PHP versions after inspecting its validation and dependency provenance. Do not hand-roll JWT validation, assume Socialite alone supplies the required OIDC checks, or copy the modified vendor tree from the sample ZIP.

Validate state before code exchange, consume the transaction once and validate token signatures, allowed algorithms, exact issuer, audience and applicable authorised-party claims, expiry, required subject and nonce. Require UserInfo `sub` to match the validated ID token subject. Support signing-key rotation through bounded trusted discovery/JWKS retrieval. Enforce configured hosts and HTTPS; never fetch an issuer or redirect supplied by the browser.

Request only the scopes needed for the confirmed claims. Confirm claim names, subject stability, PKCE, provider reauthentication and logout before declaring sandbox integration complete. Email scope is not proof that an email is returned or verified. Provider cancellation/failure produces a safe local message, not a permissive fallback callback.

### 5. Provide independent email OTP fallback

Only active enrolled users with an active MyDigital ID link and an explicitly verified unique login email qualify. OTP eligibility checks local link status, not live provider availability. The destination comes from the verified contact record; the user cannot substitute a new destination during login.

Proposed sandbox defaults:

| Control | Proposal |
|---|---|
| Code | Six cryptographically random decimal digits |
| Validity | Five minutes |
| Wrong attempts | Five per challenge, with additional rolling contact/IP limits |
| Resend | Minimum 60 seconds; five sends per contact in 15 minutes; IP and global ceilings |
| Replacement | New generation invalidates previous codes without resetting abuse counters |
| Binding | User, contact version, purpose, opaque challenge and requesting browser |
| Consumption | Atomic single-use verification and login; recheck eligibility inside the transaction |
| Storage | Domain-separated keyed digest with key version; no plaintext in database, sessions or logs |
| Delivery | Encrypted short-lived queue payload if queued; suppress expired/replaced messages |

Use generic responses and equivalent request processing for eligible and ineligible addresses; expose no account identifiers or destination hints before eligibility is established. Rate-limit initiation and verification separately. Delivery acceptance does not mean delivery or verification. No successful login on SMTP failure, challenge replay, contact change or account suspension.

Login and enrolment proof challenges have separate purposes and cannot substitute for each other. Do not overload `core.public_access_tokens` in a way that changes existing magic-link semantics.

### 6. Preserve session, staff and audit boundaries

Regenerate the session after successful authentication; record method and authentication time. OTP is not labelled MFA or fresh MyDigital ID verification. Use secure, HttpOnly, appropriately SameSite cookies, CSRF protection and `no-store` on challenge/callback surfaces. No remember-me for sandbox login. Proposed pilot session limits are 30 minutes idle and eight hours absolute, enforced without changing global staff session behaviour.

Existing POST logout always invalidates the local session and CSRF state. Provider logout is a separately confirmed redirect flow; its failure cannot keep the local account signed in or clear unrelated browser storage. Hold any short-lived logout token server-side only if the confirmed contract requires it, encrypted with a defined lifetime.

Record link initiation/outcome, contact verification, SSO/OTP authentication outcome, throttling and logout using the existing append-only audit model where compatible. Store event/category, internal reference, time and outcome; no raw codes, tokens, secrets, full email addresses or NRIC in event payloads. Link/contact state mutations and their successful audit records commit together. Delivery/rejected attempts may use bounded operational events to avoid audit flooding. Audit retention and minimal network metadata require an explicit operational policy before real sandbox participants.

### 7. Keep activation reversible and sandbox-only

Require default-off feature settings **and** a verified non-production environment, configured sandbox issuer/client/callback and an explicit participant allowlist. Reject enabling this milestone in production. The supplied MBSP realm is not classified as sandbox without provider confirmation.

Existing password, Google, registration, magic-link and recovery behaviour remains available during this pilot. This ADR does not retire or supersede ADR-304/355; their future production replacement requires another accepted decision. The new endpoints cannot create guests or bypass eligibility, even if an existing endpoint can create a guest under existing policy.

Disable enrolment and login independently where useful, with a master switch that blocks new flows and pending callback/OTP completion. Logout stays available. A separate containment procedure can revoke already issued pilot sessions; simply turning off login does not silently terminate valid sessions. Keep additive data on rollback and do not automatically unlink users, reset credentials or restore deprecated methods.

## Prerequisites and unresolved decisions

- Owner review and local implementation instruction completed on 2026-11-17. Real participant data/retention, provider use and production activation remain outside that instruction.
- Provider confirmation of sandbox entitlement, actual issuer/discovery/JWKS, client-authentication method, redirect/logout URLs, claim schema, subject stability, PKCE and mobile browser behaviour. Unsupported required security controls block the real sandbox demonstration.
- Agreement on participant identity mapping, permitted test data, email destination allowlist and a sandbox SMTP arrangement. Use synthetic fixtures for automated tests; do not import production records or official documents into sandbox casually.
- Separate provider/ICT confirmation before turning the future iNDECS-hosted service into an MBSP-wide identity broker. No downstream OIDC server is delivered here.
- OIDC package selection with reproducible dependencies, audit review and no reliance on untracked vendor edits.

## Consequences and alternatives

This milestone proves account continuity without production cut-over or company complexity. It deliberately requires existing account and mailbox control; users without either need later assisted recovery. Separate proof/link/contact records add schema and operational work but make replay, concurrency, future membership and rollback boundaries explicit.

Rejected alternatives: automatic matching by email/NRIC, new accounts created by OTP, shared company credentials, using a face-match endpoint as SSO, copying sample vendor files, building a council-wide identity platform in the first milestone, or removing existing login before migration evidence exists.

## Completion evidence

The milestone is complete only when automated negative and concurrency checks pass, existing public/staff and module regressions stay green on PostgreSQL, and an authorised real sandbox demonstration proves both login methods reach the same account and existing draft. A fake provider demonstrates application logic, not provider interoperability. Until the latter succeeds, report the status as implementation ready for sandbox verification.

## References

- [Implementation plan](../superpowers/plans/2026-11-17-mydigital-id-sandbox-milestone.md)
- [Identity and integration boundary](../bangunan/12-identity-and-integration-contracts.md), interpreted with subsequent AIRS merge decisions.
- Officer-provided MyID Access SSO overview, MyID Verify V1/V2 guides and `sso_example_mbsp.zip`; local reference material, not committed credentials or redistributable source pages.
- [Provider Laravel example](https://github.com/MyIDSSO/Laravel10-php8).
- [OpenID Connect Core](https://openid.net/specs/openid-connect-core-1_0.html), particularly ID token validation and UserInfo subject consistency.
- [RFC 9700](https://www.rfc-editor.org/rfc/rfc9700.html), OAuth security guidance.

The broader owner-review document is titled *MyDigital ID and company access design for iNDECS*, version 0.1, 17 November 2026. This ADR does not accept its remaining company or production policy proposals.
