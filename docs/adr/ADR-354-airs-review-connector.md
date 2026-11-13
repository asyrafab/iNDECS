# ADR-354: Integrate AIRS as the iNDECS review service

Date: 2026-11-04. Status: Accepted implementation of ADR-352. AMIRA work remains deferred.

## Context

The owner requests the AIRS integration now. iNDECS has document/record permissions but no payment-confirmed public submission path; AIRS has an LJUP review engine and a session-based prototype API. Existing staff registration is not proof of paid formal submission. Bangunan review rules are not activated.

## Decision

Implement a service-authenticated `/api/v1/reviews` contract in AIRS and a shared iNDECS connector, persistent review requests, officer report/evidence access and feedback. Keep AIRS deployed separately and use its existing engine; do not read its database from iNDECS. Use a per-integration server-side token, bounded requests and HTTPS except loopback development. This token authorises the iNDECS service, not independent browser access or a substitute for future user OIDC.

Pin reviews to an immutable `core.accepted_submissions` record containing a document manifest/hash, verified-payment or approved-waiver reference, clearance time and acceptance time. The future payment/submission workflow must create that record only after independent confirmation. This increment exposes no endpoint, command or form for marking payment cleared or manufacturing acceptance. Do not infer clearance from `submitted` status, officer registration or an uploaded receipt. Reject unpaid/missing clearance, unsafe files, stale manifests and unconfigured application/module mappings.

Version one transfers immutable document bytes as multipart files plus their checksum manifest. This replaces signed download URLs for intake in this increment, avoids server-side URL fetching, and introduces no shared filesystem dependency. AIRS verifies every file against the manifest and pins its module/rule version. A stable request ID deduplicates retries; conflicting reuse is rejected. Poll status/findings with an explicit synchronisation command and officer refresh; durable requests survive outages. Signed push callbacks remain a later transport option under the same versioned correlation contract.

AIRS service-owned cases are unavailable through its legacy public/session routes. iNDECS enforces record/department/type/district access and reviewer permissions, shows evidence separately from advisory findings, retains original findings and appends attributed/idempotent feedback. No returned finding or score changes application stage/status or issues a letter. Stale results remain history and cannot receive current-round feedback. iNDECS evidence downloads use its own permission-checked originals, not unauthenticated AIRS links.

## Consequences

Add additive PostgreSQL migrations in both repositories with data-preserving rollback refusal after integration records exist. The connector can be tested end to end without model credits using the existing fake engine. Fee tariffs, public intake, payment gateway, validated department rule packs and browser SSO remain explicit launch gates; do not create a second public AIRS submission journey or advertise unsupported reviews as live. Populate no payment/acceptance records in operational data during integration testing.
