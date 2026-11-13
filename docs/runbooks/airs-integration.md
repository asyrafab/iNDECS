# AIRS review integration

Implemented under [ADR-354](../adr/ADR-354-airs-review-connector.md), 2026-11-04. AMIRA remains independent and is not changed by this increment.

## Available behaviour

The iNDECS application chooser exposes an AIRS review workspace to system administrators at `/airs`. Bangunan and Perancang case pages link to their permission-checked AIRS history. Existing application access, type/department/district scopes and reviewer permissions govern every result, document and feedback action. The workspace is not another public submission form.

AIRS accepts service-authenticated version-one review requests, validates the manifest and every document checksum, queues its existing review engine, returns correlated progress/findings and accepts attributed/idempotent officer feedback. Service-owned reviews cannot be opened through AIRS's legacy public/session URLs. Original AI findings remain unchanged. iNDECS downloads source evidence from its own immutable document revisions after permission/integrity checks, rather than linking to an unauthenticated AIRS document route.

## Payment and activation boundary

`core.accepted_submissions` is an immutable, generic acceptance ledger. A future verified-payment/submission workflow must record the application, exact canonical document manifest/hash, payment-clearance or approved-waiver reference, clearance time and acceptance time. Database constraints and append-only triggers protect this proof. There is no endpoint, command or form in this increment to manufacture acceptance, mark payment paid or bypass fees. Do not populate this ledger manually to enable reviews. Test fixtures are confined to `indecs_test`.

Staff registration, `submitted` status and an uploaded payment document are not payment proof. Missing clearance, changed/unsafe files, stale document sets and missing application/module configuration prevent dispatch. Every document round requires its own accepted version. Fee amounts, public submission/payment integration, gateway confirmation and validated department rule packs remain launch gates. Current AIRS `BUILDING_PLAN`/`PLANNING_PERMISSION` slots do not imply working rules; unsupported requests become blocked without repeated engine calls. LJUP is the existing review engine, not a substitute for Bangunan rules.

## Setup and deployment

1. Rehearse and back up the target environment under the platform deployment procedure.
2. Apply only the additive iNDECS migration `2026_11_04_000100_create_airs_integration.php` and AIRS migration `2026_11_04_000100_add_indecs_review_contract.php`. Neither migrates legacy records or enables public submission. Rollback refuses once review/acceptance evidence exists; use a forward fix thereafter.
3. Generate a dedicated integration token; configure the same value server-side as `AIRS_SERVICE_TOKEN` in iNDECS and `INDECS_SERVICE_TOKEN` in AIRS. Do not reuse staff passwords, model keys or AMIRA tokens. iNDECS also needs `AIRS_ENABLED=true` and `AIRS_SERVICE_URL`; AIRS needs `INDECS_INTEGRATION_ENABLED=true`. Clear/rebuild cached configuration through the normal release process. Secrets remain in untracked environment configuration.
4. Use HTTPS and internal network restrictions in deployment. HTTP is allowed only for loopback in local/testing iNDECS environments. This service token is separate from future browser OIDC; it never appears in rendered pages or browser requests.
5. Run `php artisan airs:check` from iNDECS for authenticated capabilities. This sends no documents and invokes no model.
6. Keep the iNDECS `airs` queue worker, AIRS review queue worker and iNDECS scheduler running under supervision. `airs:sync` runs every minute and schedules at most 20 active requests plus pending feedback; queue locks prevent overlapping requests for the same review. Run the iNDECS connector with `php artisan queue:work airs --queue=airs --timeout=110` (visibility 180 seconds), and AIRS with `php artisan queue:work indecs --queue=indecs,default --timeout=900` (visibility 1000 seconds). The AIRS Windows launcher includes these queues; restart it after updating. Operator refresh is also available.

No existing `.env`, credentials or operational records are copied from one system to another. Only the dedicated service token is shared for the intended server connection. An inactive connector retains truthful availability messages.

## Wire contract and resilience

All AIRS endpoints are under `/api/v1/`, protected by its integration middleware and request throttling. The first transport uses multipart immutable files plus a JSON `manifest` (maximum 64 KiB), rather than signed download URLs. No URL-fetching or shared filesystem/database access is involved. Each manifest contains request/application/submission identifiers, module, selected lighting types, clearance/acceptance timestamps/reference, and document ID/revision/name/MIME/size/SHA-256 entries. iNDECS bounds a packet to 50 files/100 MiB and AIRS applies its own file/page/processing limits.

| Endpoint | Purpose |
|---|---|
| `GET /capabilities` | Authenticated API/module availability without AI |
| `POST /reviews` | Idempotent accepted-submission intake; returns review/version IDs |
| `GET /reviews/{id}` or `/reviews/{id}/findings` | Correlated status, pinned rule version, original findings, page/source-document evidence and external officer feedback |
| `POST /reviews/{id}/findings/{finding}/feedback` | Attributed append-only feedback, deduplicated by event ID; positive feedback requires evidence |

The request ID is stable across ambiguous timeouts/retries. Conflicting reuse is refused. Multipart transfers are not retried inside the HTTP client with exhausted streams; the queue reopens immutable files for each attempt. Requests stay durable during outages. HTTP 409/413/422 blocks a package for operator/configuration attention; other connection failures retain the original request for retry. Changed packages before initial dispatch are superseded. Historical results remain readable, but only the latest accepted/current document round may receive feedback. No AIRS result writes application status/history, confirms payment or issues a letter.

Polling is the implemented result transport; signed push callbacks remain a later option under the same correlation contract. Requests and findings carry exact submission/hash/module identities; mismatches and unsupported positive findings are rejected. Paid AI pre-submission classification is not enabled. Free preparation checks and AMIRA remain separate.

## Verification

Run `php artisan test`, `php vendor/bin/pint --test` and `php artisan view:cache` in iNDECS. Run the AIRS suite with its dedicated `airs_test` PostgreSQL configuration and fake provider; `IndecsIntegrationTest` exercises intake/auth/idempotency/checksums/module gates/evidence/feedback/legacy-access isolation. Never run those migrations against operational databases as a test substitute. The connector is shared core/integration capability; department fields remain in extension tables.
