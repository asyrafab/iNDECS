# 12. Identity and Integration Contracts

Version 2.0, 2026-09-18. Decision records: ADR-203, ADR-204.

## 1. Principles

1. **One identity.** A person has one account across iNDECS and AIRS. E-Submission accounts are linked, not merged, because its identity lives in ArcGIS Portal.
2. **Versioned APIs, no shared databases.** Each system owns its data. Contracts are HTTP APIs with OpenAPI documents, versioned under `/api/v1/`.
3. **Every system runs alone.** If AIRS is down, iNDECS queues the review request. If iNDECS is down, AIRS keeps working on cases it already holds. If E-Submission is down, iNDECS shows the last known spatial status.
4. **Events out, queries in.** State changes are published as signed webhooks with retry and dead-letter; lookups are synchronous REST calls with caching.
5. **Personal data minimised.** Each contract lists the fields it carries and why.

## 2. Identity

### 2.1 iNDECS as OpenID Connect provider

- Laravel Passport in iNDECS issues OIDC ID tokens and OAuth 2.1 access tokens. Authorisation code flow with PKCE for browser clients; client credentials for system-to-system calls.
- **Staff identity source:** the MBSP staff credential API that AIRS already integrates (`MbspStaffAuthenticator`) is moved into iNDECS core as the staff authenticator. If ICT enables Active Directory or an existing SSO, it is added as a second source behind the same interface. Staff never have a separate iNDECS password.
- **Public identity:** email plus phone OTP registration, passwordless **shadow accounts** created on first submission, **magic links** for access without a password, and later account claiming. This is the AIRS design lifted into core. Google sign-in is kept as an optional convenience.
- **Companies:** registered by SSM number, deduplicated on it, shown to officers as applicant-declared. Consultants (PSPs) additionally verified against LAM and BEM registers with manual approval.
- **Claims carried in the token:** subject ID, account type (staff, public, PSP, system), department, daerah scopes, roles as permission group names, company memberships, MFA level. Applications authorise locally from these claims.

### 2.2 AIRS as a relying party

- AIRS replaces its local login with OIDC against iNDECS. Its `users` table becomes a local cache keyed by iNDECS subject ID. Its roles (`MBSP_CLERK`, `MBSP_OFFICER`, `MBSP_APPROVAL_OFFICER`, `ADMIN`, `PUBLIC_APPLICANT`) are mapped from iNDECS permission groups.
- Standalone mode: if the iNDECS identity provider is unreachable, AIRS accepts existing sessions and falls back to its cached staff authenticator for staff only. Public login waits.

### 2.3 E-Submission linkage

- E-Submission users are ArcGIS Portal accounts managed by the Portal admin. iNDECS stores a link record: iNDECS subject ID, E-Submission username, identity number (IC or SSM, hashed), verified date.
- Linking is done once, by the user entering their E-Submission username in iNDECS and confirming through an email sent to the address registered in both systems, or by an admin.
- If Esri can federate ArcGIS Portal with an external OIDC provider (Portal supports SAML and OIDC enterprise logins), iNDECS becomes that provider and the link record becomes redundant. This is an open question to Esri.

## 3. iNDECS to AIRS: plan review contract

| Step | Direction | Call | Payload |
|---|---|---|---|
| 1 | iNDECS to AIRS | `POST /api/v1/reviews` | iNDECS application ID and reference, review module code (`BUILDING_PLAN`), family and building type for rule applicability, document manifest with signed download URLs, callback URL |
| 2 | AIRS to iNDECS | Webhook `review.status_changed` | AIRS case ID, processing state, progress percentage |
| 3 | AIRS to iNDECS | Webhook `review.completed` | AIRS case ID, overall result, finding count by status, report URL, findings summary array (rule ID, status, confidence, severity, evidence count) |
| 4 | iNDECS to AIRS | `GET /api/v1/reviews/{id}/findings` | Full findings with evidence references for display inside the iNDECS application workspace |
| 5 | Officer action | In AIRS or iNDECS | Officer confirms or corrects findings. If done in iNDECS, `POST /api/v1/reviews/{id}/findings/{fid}/feedback` writes back to AIRS so the calibration signal is not lost |
| 6 | iNDECS | Internal | Confirmed findings become a compliance checklist on the application; the decision stays in iNDECS |

Additional intake use: `POST /api/v1/documents/classify-and-extract` sends a single uploaded document and receives document type, extracted application profile fields with confidence and evidence pages. iNDECS uses this for auto-fill with officer confirmation. AIRS must expose its existing classification and profile extraction as this endpoint.

AIRS stays the system of record for review cases, evidence, rules and AI runs. iNDECS stores the AIRS case ID, result, and the confirmed checklist.

## 4. iNDECS to E-Submission: spatial contract

The vendor system exposes what its URS defines; the contract below is what iNDECS needs and must be negotiated with Esri Malaysia.

| Need | Preferred mechanism | Fallback |
|---|---|---|
| Know that a PSP submitted spatial data for an application | E-Submission calls an iNDECS webhook on status change (`Draf`, `Menunggu Pengesahan`, `Diterima`, `Mohon Pembetulan`, `Ditutup`) with the iNDECS application reference entered by the PSP | iNDECS polls the E-Submission database view or ArcGIS feature service on a schedule |
| Reference the resulting GIS features | E-Submission returns ArcGIS GlobalIDs of created features; iNDECS stores them on `sites` and `units` | Officer pastes GlobalID or selects on map |
| Show the plan on a map inside iNDECS | ArcGIS Enterprise feature service consumed by an embedded map in the iNDECS workspace | Link out to Experience Builder |
| Pre-fill E-Submission with iNDECS application data | E-Submission accepts the iNDECS reference as a required field (its URS already has application details on the new-submission form) | Manual entry |

A change request to the Esri scope is likely needed for the webhook and the iNDECS reference field. Raise it during Phase B so it lands before Phase C.

## 5. External systems

| System | Direction | Purpose | Mechanism | Phase |
|---|---|---|---|---|
| OSC3Plus | Inbound submissions, outbound decisions | Full building plans and planning permission routed via the national OSC | Unknown; three scenarios (web service, database extract, file import) as in version 1 | C, discovery in A |
| MBSPpay | Outbound invoice, inbound callback | All fees and deposits | Signed redirect, idempotent webhook, settlement reconciliation. AIRS's billing design (RM150 gate, waiver rules, three-month cancellation) becomes the core pattern | B |
| Penilaian | Outbound events | New or altered unit for assessment | `unit.ccc_issued`, `unit.altered`, `unit.demolished` webhooks, or pull via read API | D |
| Penamaan Jalan dan Penomboran Bangunan | Outbound event, inbound address | Official address for a new unit | Webhook plus callback; `unit.address_assigned` republished | D |
| Pelesenan | Inbound lookup | Valid CCC and approved use for a premise | Read API by Unit ID or address | D |
| GIS unit | Bidirectional | Lot geometry lookup; building layer publishing | ArcGIS REST feature services; PostGIS locally | C |
| Penguatkuasaan | Outbound handover | Notices needing enforcement action | Webhook or restricted iNDECS role if they have no system | B |
| SMS and email | Outbound | Notifications | Council gateway API or SMTP | B |
| LAM and BEM | Outbound lookup | Consultant verification | Lookup service or periodic list import | C |
| WiseCore suite (Penilaian, Lesen and others) | Read | Legacy cross-reference during transition | Read-only SQL Server views synced nightly | A |

## 6. Read API

Versioned REST under `/api/v1/` in iNDECS, OpenAPI documented, client-credentials tokens per consuming system, rate-limited, read-only. Resources: `units`, `lots`, `applications` (summary), `letters/verify/{token}` (public, minimal), `ccc` by unit. Field-level filtering removes personal data for consumers that do not need it.

## 7. Webhook standard

All outbound events share one envelope: event ID (ULID), event type, occurred-at, producer, subject reference, payload, HMAC-SHA256 signature over the body with a per-consumer secret. Delivery with exponential backoff up to 24 hours, then dead-letter with an operations alert. Consumers must be idempotent on event ID. The `core.domain_events` table is the replayable source.

## Open questions

1. Can ArcGIS Portal federate with the iNDECS OIDC provider? Owner: Esri Malaysia via programme owner. Needed by: Phase C.
2. Will Esri accept a change request for status webhooks and an iNDECS reference field? Owner: programme owner. Needed by: Phase B month 6.
3. OSC3Plus integration scenario. Owner: programme owner with state OSC unit. Needed by: Phase C.
4. Whether Penguatkuasaan has a case system. Owner: product owner. Needed by: Phase B notis cut-over.
