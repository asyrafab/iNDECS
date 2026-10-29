# 11. iNDECS Core Roadmap

Version 2.2, 2026-10-29. Decision records: ADR-201 to ADR-213. Department modules are built concurrently (ADR-212).

## 1. Purpose

iNDECS core today is a registration and completeness slice for PKM. Bangunan needs far more from the platform: a workflow engine, real RBAC, payments, notifications, SLA, letters, and a units registry. Management has brought Bangunan forward, and the department modules (Perancang, Bangunan, Kejuruteraan, Landskap) and eOSC+ are now built **concurrently** (ADR-212): each capability is built when the first module needs it, in the core, generic enough that every other module inherits it without rework.

This document lists those capabilities, the order they are built, and the platform baseline (database, security) that comes first.

## 2. Platform baseline

### 2.1 Database: PostgreSQL 16 with PostGIS

- One PostgreSQL cluster in the MBSP data centre. iNDECS is one database with schemas `core`, `bangunan`, `perancang`, `kejuruteraan`, `landskap`, `ai`, `integration`. AIRS keeps its own database in the same cluster.
- Extensions: PostGIS (lot and footprint geometry), `pgvector` (semantic search for the internal AI layer), `pg_trgm` (fuzzy name and lot matching for duplicate detection).
- Migration from SQLite: Laravel migrations are re-run against PostgreSQL; the PKM slice data is small enough to re-seed. AIRS migrates the same way. Both apps must drop any SQLite-specific SQL.
- Row-level security is available for daerah scoping if application-level scoping proves insufficient, but the first implementation scopes in Eloquent global scopes.
- Backups: nightly base backup plus WAL archiving to a separate volume; restore tested quarterly.

### 2.2 Security baseline

Carried over from version 1 and still required. Aligned to OWASP ASVS Level 2.

- Argon2id password hashing (Laravel default is bcrypt; switch the hasher), 12-character minimum with blocklist, no forced rotation.
- MFA (TOTP) mandatory for approving, issuing and administrative roles.
- Lockout after five failures, session timeout 30 minutes staff and 60 portal, sessions in Redis.
- CSRF, CSP with nonces, TLS 1.2+ only, HSTS, secure cookies.
- Uploads sniffed by content, scanned with ClamAV in a queue worker, stored in MinIO outside the web root, served through signed URLs.
- Secrets in a secrets store, never in the repository. The `.env` files currently on disk in both repositories with live keys are rotated as part of the hygiene work.
- Append-only audit: the existing `audit_logs` table gains a database rule denying UPDATE and DELETE to the application role, plus a nightly hash chain.

The legacy system's critical findings (plaintext passwords, shared SQL login on a share, no-op audit, string-built SQL) are closed by retiring it. In the interim, the legacy share ACL is tightened to the AD group that runs the WinForms app and the debug artefacts are removed from the share.

## 3. Core capabilities

The "why" column records the original Bangunan driver. Under ADR-212 a capability is built when any concurrent module first needs it.

| # | Capability | Why Bangunan needs it first | Design summary | Effort |
|---|---|---|---|---|
| C1 | **Identity provider** | Public applicants, PSPs and staff need one login across iNDECS and AIRS | iNDECS issues OpenID Connect tokens (Laravel Passport). Staff authenticate via the existing MBSP staff API that AIRS already integrates, or Active Directory if ICT enables it. Public accounts, shadow accounts and magic links lifted from the AIRS design. Details in document 12 | M |
| C2 | **RBAC and scoping** | The one hard-coded PKM access check cannot express Bangunan roles | Roles and permissions tables (Spatie permission package), permissions named `bangunan.application.approve` and so on. Department, daerah and application-type scoping as Eloquent global scopes. Separation of duties enforced in workflow, not in views | M |
| C3 | **Workflow engine** | Bangunan has five families with different stages; PKM has one hand-written path | Configuration tables per the iNDECS overview: `workflow_templates`, `workflow_stages`, `workflow_transitions` with guards (role, required artefacts, fee paid, meeting decision recorded). A `WorkflowService::transition()` replaces per-module transition methods. Stage and status history stay append-only. PKM is migrated onto the engine in module rollout Phase 2, after Bangunan acceptance | L |
| C4 | **Documents and requirements** | Exists; extend | Add virus scanning, MinIO storage, document classification hook for AIRS, and per-family requirement catalogues as seed data | S |
| C5 | **Fees and payments** | Every Bangunan family charges fees and deposits | `fee_rules` with rate types and effective dates, `fee_assessments`, `payments`, `payment_allocations`, deposits with refund workflow. MBSPpay adapter with signed redirect, idempotent webhook, daily reconciliation. Counter receipt entry as fallback | M |
| C6 | **Reference numbers** | Exists; generalise | The PKM generator already handles `PKM/2026/00001`; add `PB` and per-family formats, plus letter, payment, notice and unit sequences with check digits | S |
| C7 | **Units registry** | The Unique ID for every building unit, needed at Bangunan CCC and by Penilaian, Pelesenan, Penamaan Jalan | `core.units` (ULID, `unit_id` like `UB-SPT-0001234-7`, lot, kind, status, official address, CCC date, geometry). Lifecycle planned, approved, under construction, CCC issued, altered, demolished. Every transition publishes a domain event. Duplicate detection tooling for legacy lots and parties using `pg_trgm` | M |
| C8 | **Tasks, SLA, notifications** | Piagam Pelanggan enforcement and applicant notices | Tasks with due dates already exist in embryo; add working-day calendar (weekends, Penang holidays, editable), stop reasons that pause the clock, escalation ladder, and a notification adapter for email and SMS with templates and delivery log. AIRS's `notification_deliveries` design is the model | M |
| C9 | **Letters and e-approval** | SKP series, CCC, permits | HTML templates rendered to PDF (dompdf, already used in AIRS; move to a headless Chromium renderer if fidelity is insufficient), approval with MFA re-prompt, document hash, verification token, public QR verification page. AIRS's `review_letters` and `verify-letter` design is lifted into core | M |
| C10 | **Meetings and decisions** | Jawatankuasa OSC and Mesyuarat YDP | Per the iNDECS overview: meetings, meeting cases, decisions with conditions; agenda and kertas pembentangan generation. The meeting-room screens (eOSC+: agenda builder, map, chairperson's decision panel, attendee mobile view, PSP video call) are designed in `docs/eosc/README.md` (draft) | M |
| C11 | **Domain events and integration bus** | Unit events to other departments, AIRS handoff, OSC3Plus | `core.domain_events` append-only table, Laravel events and queued listeners, outbound webhooks with retry and dead-letter, replay tool | S |
| C12 | **Dashboards and reports** | Management visibility, KPKT returns | Saved report definitions with parameters, CSV and PDF export, role-based dashboards | M |
| C13 | **GIS** | Lot geometry, footprints, map search | PostGIS storage; ArcGIS Enterprise feature services consumed for cadastral lookup and published for building layer; E-Submission feature references stored on applications | M |
| C14 | **Internal AI services** | Analysis and working papers | Separate document 14 | L |

## 4. Sequencing

**Concurrent delivery (ADR-212, 2026-10-29).** iNDECS Perancang, Bangunan, Kejuruteraan, Landskap and eOSC+ are built at the same time on the shared core. Each project keeps its own documentation folder under `docs/` with its own specification, backlog and acceptance. The sequential module rollout below (ADR-211) is superseded and kept for history; milestones A to E remain as platform milestones and as the Bangunan delivery plan, not as a department order.

Superseded: the module rollout order was **Phase 1: Bangunan**, followed by **Phase 2: PKM (Perancang)** (ADR-211, 2026-10-27).

| Module rollout phase | Scope | Entry and exit |
|---|---|---|
| **1. Bangunan** | Shared platform foundation and Bangunan delivery through milestones A to D | Exit: milestones B, C and D accepted, legacy retired, online Bangunan processing proven and operational handover accepted |
| **2. PKM (Perancang)** | Migrate the existing PKM slice to the shared workflow engine; configure PKM roles, stages, requirements, fees, letters and AIRS rules as applicable | Starts after Phase 1 acceptance, in milestone E; exit: PKM records and history reconciled, permissions verified, and a representative PKM application accepted end to end |

Under ADR-212, PKM workflow migration runs alongside Bangunan instead of after it, and neither gates the other's exit. Kejuruteraan and Landskap may start whenever they are staffed. Its detailed scope, effort and dates must be estimated separately; the Bangunan estimate below does not include completion of PKM.

| Phase | Months | Core work | Bangunan work | Exit |
|---|---|---|---|---|
| **A. Foundation** | 0 to 3 | Hygiene (document 15), PostgreSQL migration of iNDECS and AIRS, C1 identity, C2 RBAC, environments, security baseline | Nightly read-only sync of `MPSP_Bangunan` into `bangunan.legacy_*`; web lookup screens in the iNDECS shell; duplicate detection into C7 | Staff use iNDECS for Bangunan lookups; one login for iNDECS and AIRS |
| **B. Bangunan operational** | 3 to 10 | C3 workflow engine, C4, C5, C6, C7, C8, C9, C10, C11 | All five families on the engine, cut over family by family (telco first, permohonan last); letters with QR; committee tabling; final migration; legacy retired | Legacy WinForms inaccessible; Bangunan fully in iNDECS |
| **C. Public and review** | 10 to 15 | Public portal in iNDECS core (accounts, tracking, appointments, fee calculator), C13 GIS | Online submission for all families; AIRS `BUILDING_PLAN` live for plan review and intake auto-fill; E-Submission link; OSC3Plus scenario; MBSPpay live | First online Bangunan application paid and approved end to end |
| **D. Intelligence and linkage** | 15 to 21 | C12 dashboards and returns, C14 internal AI, unit events to Penilaian, Pelesenan, Penamaan Jalan, GIS layer | Working paper drafting, compliance summaries, consultant scoring, extension-plan designer | First unit auto-registered downstream; AI drafts accepted by officers |
| **E. Field** | 21 onward | Self-hosted AI when infrastructure allows (PKM workflow migration moved to run concurrently, ADR-212) | Mobile inspections | Field inspections live |

**Realism note.** This plan is roughly 21 months of full-time work for a small team. With one developer it stretches beyond three years or shrinks in scope. The proposal to management must carry a request for at least one additional developer and name the scope that drops if that is refused: Phase D and E content first, then the public portal moves after the internal system is stable.

## 5. What Perancang, Kejuruteraan and Landskap inherit

Everything in section 3 is built in `core`. With concurrent delivery (ADR-212) the PKM slice is migrated onto the workflow engine alongside Bangunan, which proves early that the core is generic. Phase B validates the engine against Bangunan's five families and PKM together. When another department starts, its module is extension tables, a family configuration, requirement catalogues, letter templates and a rule pack for AIRS, which is exactly the shape the iNDECS overview prescribes.

## 6. PKM Phase 2 delivery backlog

Draft planning baseline, 2026-10-27; entry conditions revised 2026-10-29 by ADR-212, which removes the wait for Bangunan acceptance. The section title keeps its original name for traceability. This backlog originally implemented ADR-211; detailed PKM business rules and estimates remain subject to discovery with Perancang. It records planned work, not completed implementation.

### Scope and prerequisites

The first PKM release preserves existing registration and completeness review, moves their workflow and history onto the shared engine, and adds only the subsequent stages confirmed by Perancang. Reuse core identity, permissions, documents, references, tasks, audit and reporting. Configure fees, letters, meetings, public tracking and AIRS only where the confirmed PKM process requires them; Bangunan's rules are not PKM defaults.

Under ADR-212, delivery starts when the core capabilities it needs exist, not after Bangunan acceptance. Before implementation, obtain the current iNDECS source and database inventory, an approved PKM process and permission matrix, a representative anonymised case set, record and attachment counts, and named Perancang acceptance officers. This workspace contains documentation and mockups; it does not contain the application source needed to execute the backlog. Preserve the existing PKM service while Bangunan is delivered.

### Work packages and estimates

Estimates below are provisional engineering planning allowances, not measured delivery commitments. They assume one full-time developer, a stable Bangunan core, reusable integration adapters and timely officer input. Effort is in developer-weeks; dependencies establish the order even if a second developer is available.

| ID | Deliverable | Depends on | Proposed owner | Effort | Evidence of completion |
|---|---|---|---|---|---|
| P2-01 | Inventory existing PKM routes, tables, states, permissions, records and attachments; confirm the release boundary and state mapping | Source access (Bangunan acceptance no longer required, ADR-212) | Developer and Perancang product owner | 1–2 | Approved scope, workflow diagram, permission matrix, migration map and acceptance case set; unresolved rules recorded |
| P2-02 | Configure PKM workflow templates, transition guards, roles and department/daerah scoping; connect the existing registration and completeness screens | P2-01 | Developer | 3–5 | Approved transitions succeed; invalid transitions and cross-scope access are denied; actor and state history are retained |
| P2-03 | Configure required documents, references, tasks and SLA; reuse applicable fees, letters, meetings, public tracking and AIRS contracts | P2-02 | Developer with Perancang officers | 2–4 | Confirmed configuration matches officer examples; applicable callbacks tolerate retries; unavailable integrations follow the agreed fallback |
| P2-04 | Rehearse migration on a restored copy; reconcile records, attachments, references and history; rehearse recovery | P2-02; P2-03 configuration fixed | Developer and BTM | 2–3 | Every source record accounted for; attachment hashes and history checked; exceptions resolved or explicitly accepted; repeat run creates no duplicates; recovery demonstrated |
| P2-05 | Run PKM acceptance and Bangunan regression checks; prepare training and operating instructions | P2-03 and P2-04 | Perancang acceptance officers, developer and Bangunan product owner | 2–3 | Representative case completes end to end; return/correction and permission-denial paths pass; existing Bangunan cases remain valid; acceptance recorded |
| P2-06 | Pilot PKM, perform the agreed cutover and support the release | P2-05 acceptance | Product owner, developer and BTM | 1–2 | Final reconciliation accepted, support ownership established, monitoring and recovery criteria recorded; pilot issues resolved before wider release |

Total provisional effort is **11–19 developer-weeks**, excluding contingency. A 25% planning reserve gives approximately **14–24 developer-weeks**. This is not a calendar start or finish date and is separate from Bangunan's indicative 21 months. Re-estimate after P2-01; new external interfaces, extensive data cleansing, new AI rule development or major core changes require separate estimates. Self-hosted AI, mobile inspection and onboarding other departments are outside this PKM release estimate.

### Acceptance and cutover

PKM may leave Phase 2 only when the Perancang product owner accepts the configured process and representative cases, data reconciliation, access controls and operating handover. Acceptance must include missing-document/correction paths, an unauthorised transition, cross-department and cross-daerah access attempts, and a complete valid case. Verify payment retries, letter verification and AIRS handoff when those features are in the approved release. Compare Bangunan regression results before and after the PKM change.

Before cutover, agree the write-freeze window and recovery decision with BTM. Record the final source snapshot and reference-sequence position; rerun reconciliation against that snapshot. Recovery must account for any PKM writes after cutover and replay or reconcile them before reopening the previous service. A database restore alone is insufficient once new applications have been accepted. Route unresolved migration exceptions to the named owner rather than silently dropping records.

The next executable package is **P2-01**, which may start now under ADR-212, in parallel with Bangunan delivery. Open planning items are recorded as PD-240 in the Malay decision register.

## Open questions

1. PostgreSQL permitted by MBSP ICT. Owner: ICT. Needed by: Phase A month 1.
2. Active Directory availability for staff SSO, or continued use of the MBSP staff API. Owner: ICT. Needed by: Phase A month 1.
3. MBSPpay integration documentation and test credentials. Owner: Jabatan Kewangan. Needed by: Phase B month 4.
4. Piagam Pelanggan durations per Bangunan family. Owner: product owner. Needed by: Phase B month 5.
5. Additional developer approval. Owner: programme owner. Needed by: end of Phase A.
