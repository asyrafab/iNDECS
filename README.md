# iNDECS

> **NADI direction — 9 November 2026:** [ADR-378](docs/adr/ADR-378-nadi-meeting-application-boundary.md) updates the earlier ADR-213 product boundary. **eOSC within iNDECS registers each paper for its assigned meeting. NADI — Navigation, Analysis, Decisions & Intelligence — is the application used within the meeting**, with eOSC as its first module and other meeting types open for later needs. Earlier descriptions of the entire live meeting as eOSC inside C10 are historical where they conflict. Existing code/identifiers remain; NADI deployment and integration require design. See [current direction](docs/eosc/nadi-direction.md).


> **AI ecosystem alignment — 9 November 2026:** [Current roles and integration boundaries](docs/integration/ai-ecosystem.md). AMIRA remains application assistance; MATRIX is the planned document specialist; MINDA is internal intelligence. Existing authority, free-precheck and payment gates remain.


## AIRS integration and applicant submission (accepted 2026-11-04)

[ADR-352](docs/adr/ADR-352-indecs-airs-submission-and-precheck.md) and the [shared integration contract](docs/integration/indecs-airs-contract.md) govern the target journey. iNDECS owns identity, drafts, formal submission, authoritative document versions, payments, corrections, assignment/SLA, notifications, official decisions and letters. AIRS is its review subsystem and owns review rules, evidence, findings and AI-run history; AMIRA assists within record permissions.

Free applicant precheck covers checklist/file usability and bounded embedded-text/local-OCR retrieval, with manual confirmation and resource limits. No paid generative AI/cloud processing, technical compliance review or readiness score is allowed before submission. Payment must clear before formal acceptance (only approved audited policy waivers); accepted submission then dispatches formal AIRS review. Fee amounts and correction-round charging remain undecided. Scores are advisory; they never reject, hide findings or change state. This documents agreed direction, not completed public submission/payment/integration functionality.

The welcome page's **Ketahui lebih lanjut** link opens the public system guide at
`/tentang-indecs`, covering departments, roles, access, current availability and FAQs.

**iNDECS — Intergrated Development Control System** (**Sistem Bersepada Kawalan Pembangunan**) is MBSP's configurable platform and main portal for development-control applications. It is one shared core with four departmental modules (Perancang, Bangunan, Kejuruteraan, Landskap), and the identity provider for a family of cooperating systems: AIRS (AI Review Studio) for plan review and the Esri E-Plan Submission System for spatial submissions.

The product vision is in [docs/OVERVIEW.md](docs/OVERVIEW.md). The binding design rules are in [AGENTS.md](AGENTS.md) and [docs/DESIGN-DIRECTION.md](docs/DESIGN-DIRECTION.md). The current programme, which brings **iNDECS Bangunan** forward and builds the core alongside it, is documented in [docs/bangunan/](docs/bangunan/README.md).

## Current capability

**Department modules are built concurrently (ADR-212, 2026-10-29): Perancang, Bangunan, Kejuruteraan, Landskap and eOSC (the OSC meeting room, [docs/eosc/](docs/eosc/README.md)) share the core and progress in parallel.** Bangunan is the most advanced. The operational Bangunan increment now includes registration for PB/PBK/PBS/PMT/TEL, case lists and scoped access, shared reference sequences and workflow, department-confirmed document catalogues, scanned private document revisions, officer verification, correction/resubmission, and guarded progression to fee assessment. Open `/bangunan` with an authorised staff account. Fees, payments and approvals remain future increments. See the [registration runbook](docs/runbooks/bangunan-registration.md) and [document-verification runbook](docs/runbooks/bangunan-document-verification.md).

Existing PKM capability is retained: the repository contains the first working slice of the **Permohonan Kebenaran Merancang (PKM)** module. Implementation decisions and discovery items for this slice are in [docs/PKM-FIRST-SLICE.md](docs/PKM-FIRST-SLICE.md).

- PKM dashboard and searchable application list
- Validated PKM registration form
- Automatic yearly references such as `PKM/2026/00001`
- Shared applicant and consultant records with submission snapshots
- Shared site and multiple cadastral-lot records
- PKM-specific land-use, density, plot-ratio, unit, storey and height fields
- Multiple development components
- First workflow transition from registration to completeness review
- Session authentication with active-user, department and PKM-role access controls
- Configurable required-document checklists by PKM category and type
- Private document storage with SHA-256 checksums and immutable version history
- Officer verification for every checklist item
- Return-for-amendment and resubmission cycles with preserved review history
- Automatic officer assignment and five-working-day completeness due date
- Immutable stage history, audit records and optimistic row versioning
- Responsive server-rendered interface in Bahasa Melayu

The model keeps shared application data separate from `app_pkm_applications` and `app_pkm_components`, so the Bangunan, Kejuruteraan and Landskap modules reuse the same core.

## Direction (decided 2026-09-18)

- **Database:** PostgreSQL 18 with PostGIS and pg_trgm — **implemented** on the local Windows service (`127.0.0.1:5434`, database `iNDECS`, consolidating `iNDECS`, `AIRS` in schema `airs`, and `MIDR` in schemas `catalogue`, `core`, `application`, `publish`). Route (`/pkm` → `/perancang`) and table (`pkm_*` → `app_pkm_*`) naming was also reconciled to `docs/DESIGN-DIRECTION.md`; PHP class names were left as-is since they name the PKM application type, which stays as-is per `AGENTS.md`.
- **Identity:** iNDECS issues OpenID Connect for the family; staff via the MBSP staff API or Active Directory, public users via shadow accounts and magic links. Not yet implemented.
- **Core capabilities** (workflow engine, RBAC, fees and payments, notifications, SLA, letters, units registry, domain events) are built in `core` in the order iNDECS Bangunan needs them. Shared references, scoped RBAC, the initial workflow and document-verification services are implemented for Bangunan. PKM workflow migration now runs alongside Bangunan (ADR-212, superseding ADR-211); fees, payments and later capabilities remain outstanding.
- **Visual direction:** Calm Intelligence, shared with AIRS.
- **Approved workspace:** navy sidebar, Google Sans, shared teal accent, responsive
  light/dark modes, local Lucide icons and reduced-motion support across staff,
  public, Perancang and Bangunan screens. AMIRA provides local page guidance;
  live AIRS conversation is not yet connected. See the
  [theme and AMIRA runbook](docs/runbooks/theme-and-amira.md).
- **Engineering hygiene** (repositories, CI against PostgreSQL, secrets management, staging, tested backups) precedes module work. Git history and the PostgreSQL move are done; CI, staging and backups are not. See `docs/bangunan/15-engineering-hygiene.md`.

## Local setup (current slice)

Requirements:

- PHP 8.3 or newer, with the `pdo_pgsql` and `pgsql` extensions enabled
- Composer 2
- Local PostgreSQL 18 + PostGIS service (`postgresql-x64-18` on `127.0.0.1:5434`)
- PHP extensions: mbstring, OpenSSL, cURL, fileinfo and ZIP

```powershell
composer install
Copy-Item .env.example .env
# Set INDECS_DEMO_PASSWORD in .env to a strong local secret before the first seed.
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000`.

Guests see the full-height iNDECS welcome entrance with three actions, a finite
architectural introduction and interactive photographic layers (ADR-335).
Homepage login opens a permission-filtered application chooser with distinct icons.
Staff 18811 uses the existing `system_admin` role for all implemented applications.
Planned modules and integrations appear as muted, unlinked tiles. Direct staff login and
protected-link redirects retain their destinations; the dashboard is at `/dashboard`.
Use `php artisan staff:superadmin 18811` for an audited, idempotent promotion of an
existing active linked staff account on another environment.
Public submission, payment and tracking remain pending; the landing page labels
this availability explicitly.

The test suite runs against a dedicated `indecs_test` database on the local PostgreSQL 18 instance (`127.0.0.1:5434`, see `phpunit.xml`). See [the local development runbook](docs/runbooks/local-development.md) for guarded migration verification.

The current interface does not require a frontend build. CSS and JavaScript are maintained as small, first-party assets under `public/`.

## Verification

Public account access is available at `/portal/login`, with registration, Google
OAuth (after client configuration) and passwordless email access. Staff continue
at `/login`. See [Google and public access setup](docs/runbooks/google-and-public-access.md).
Local emails are captured by Mailpit at `http://localhost:8025`. Public application
submission forms and case correspondence remain pending; these routes implement
identity only.

```powershell
php vendor/bin/pint --test
php artisan test
php artisan view:cache
```

## Important deployment note

This is an internal pilot slice, not a production release. Local authentication and PKM role/department authorization are implemented, but MBSP single sign-on, document malware scanning, production object storage, key management, retention rules, backup policy, and ArcGIS credentials must be implemented before the application is exposed beyond a controlled development environment. The programme documentation under `docs/bangunan/` sets out how and when each is delivered.

## Independent AMIRA backend (2026-11-04)

AMIRA backend ownership was moved to C:\Users\Asyra\Documents\AMIRA under ADR-353 (2026-11-04). It is an independent prepared-guidance/catalogue service; conversation, model inference and record retrieval remain unimplemented. AIRS retains its review AiProvider and UI/assets. iNDECS keeps local guidance with no new remote dependency. Future assistant APIs belong to AMIRA, with scoped source APIs and iNDECS identity; free applicant guidance stays local and uses no paid AI.

## AIRS review module (in-process, 2026-11-14)

The AIRS review engine now lives inside iNDECS under `app/Airs` (tables in schema `airs`), replacing the earlier HTTP connector of ADR-354. Design: [AIRS module merge](docs/superpowers/specs/2026-11-13-airs-module-merge-design.md); engine plan: [Plan 1](docs/superpowers/plans/2026-11-13-airs-engine-module.md). `App\Airs\CheckService` is the only entry point: it needs a payment `CheckClearance`, allows at most two completed checks per application (a failed check is rerun without being counted) and only enables the LJUP (Jabatan Kejuruteraan) module. Scores are advisory; the configured threshold only records a shadow outcome.

Applicant journey ([Plan 2](docs/superpowers/plans/2026-11-14-airs-applicant-check-journey.md)): an LJUP draft (Jabatan Kejuruteraan) declares its lighting types, uses the free precheck, then pays. Payment creates the formal application and runs check 1; the applicant sees an applicant-safe result and may amend once within 5 working days (extendable once by 5) to run check 2, or submit as-is. The application then hands over to JKEJ (`core.accepted_submissions`, with missing required documents flagged, never rejected).

Officer workspace ([Plan 3](docs/superpowers/plans/2026-11-14-airs-officer-workspace.md)): JKEJ officers use `/kejuruteraan` to read full AIRS detail, comment per finding and review MATRIX-extracted fields (attributed, append-only), grant audited fee waivers, and export corrections as MATRIX candidate lessons. Decision record: [ADR-383](docs/adr/ADR-383-airs-merged-into-indecs.md); operations: [runbook](docs/runbooks/airs-integration.md).

Setup:

- Python 3 with `python_worker/requirements.txt` (PyMuPDF, Pillow); set `AIRS_PYTHON_BINARY` if it is not `.venv/Scripts/python.exe`.
- Run a worker for the `airs` queue: `php artisan queue:work --queue=airs`, and the scheduler (`airs:recover-checks` runs every minute).
- `AIRS_AI_PROVIDER` = `fake` (default), `openai` or `matrix`. MATRIX also needs `AIRS_MATRIX_ENABLED=true`, Node and the `../MATRIX/runtime` worker; check with `php artisan matrix:status`. Budgets: `AIRS_MATRIX_REQUEST_BUDGET_USD` (0.25) and `AIRS_MATRIX_REVIEW_BUDGET_USD` (5).
- The real Green Camour acceptance test: `PLAN_REVIEW_GREEN_CAMOUR_PATH="<folder of the 12 PDFs>" php artisan test --filter=GreenCamourAcceptanceTest` (skipped when unset; the package is not committed).
- The local `iNDECS` database may still hold the old standalone AIRS tables in schema `airs`; they must be removed before `php artisan migrate` can create the module tables.
- Fee: `AIRS_CHECK_FEE_SEN` (unset disables payment; the amount is not yet decided). `AIRS_PAYMENT_DRIVER=staging` is the only driver and is refused in production until the MBSP payment gateway is integrated.
- LJUP checklist: JKEJ supplies it; publish with `php artisan catalogue:publish LJUP <file.json> --actor=<officer e-mail>` (a `senior_engineering_officer`).
- Scheduler also runs `paid-check:hand-over-expired` every five minutes. Public holidays for working-day deadlines go in `core.public_holidays`.
- The database session timezone is pinned to UTC (`DB_TIMEZONE`, ADR-314); rows written earlier under a Malaysia-time session read back 8 hours off.
