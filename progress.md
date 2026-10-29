# iNDECS Progress

Last updated: 2026-10-29.

## eOSC+ Plan 1: foundation and agenda (ADR-314)

Implemented 2026-10-29 on branch `feat/eosc-foundation` (not yet merged), inside the iNDECS Laravel application as the first part of core capability C10.

- Added additive migration `2026_10_29_000000_create_osc_meetings_and_agenda.php` (meetings, members with one chair per meeting, agenda items linked or standalone, versioned papers, published agenda versions, department endorsement settings) and `OscSeeder` (roles `osc_secretary`, `osc_steno`; permissions `osc.meeting.view/manage`, `osc.agenda.propose/endorse` and grants). No accounts or meetings seeded.
- Added `app/Services/Osc` (MeetingService, AgendaService, AgendaPaperService, OscAccess) and `/osc` screens: meeting creation with per-meeting chair and cut-offs, linked proposals from Bangunan/PKM pages, standalone items, optional head-of-department endorsement, secretary accept/return/reorder/publish, versioned agenda with change list, link-only email notification, ClamAV-scanned PDF papers (50 MB, versioned, views audited). Agenda changes refused on closed meetings; visibility limited by role. Staff accounts only until Plan 4.
- Added `docs/runbooks/eosc-agenda.md` and ADR-314. Design, plan and runbook mirrored to the eOSC+ folder.

- Final-review fixes: eOSC+-only staff and meeting participants can sign in and land on `/osc`; the meeting list follows the per-meeting view rule (proposers, endorsers) with an endorsement count; accept/return bump the meeting row version; meeting times entered and shown in Malaysian local time (`app.display_timezone`, stored UTC); integrity and scan failures logged; return confirmation fixed.

Verification: **199 tests, 1311 assertions passed** on a full `php artisan test` run.

Remaining: review and merge; Plan 2 (map) is next. No map, decision panel, attendee access or legacy migration is claimed.

## Concurrent modules and eOSC+ design (ADR-212, ADR-213)

Documentation only, 2026-10-29. No code changed.

- ADR-212: Perancang, Bangunan, Kejuruteraan, Landskap and eOSC+ are built concurrently, each with its own `docs/` folder; supersedes ADR-211's Bangunan-then-PKM order. Roadmap, README, DESIGN-DIRECTION, AGENTS, runbooks and the Bangunan programme index updated.
- ADR-213: eOSC+ (OSC meeting room) is core capability C10 inside iNDECS, with a meeting map on replicated PostGIS layers. Draft design in `docs/eosc/README.md`, still in brainstorming and not approved for build.
- The same programme documents are mirrored in the eBangunan documentation set.

## Shared document verification for Bangunan (ADR-310)

Implemented 2026-10-28 on the existing Bangunan/core review branch:

- Added shared `core.document_catalogues`, case-specific `core.document_checklists` and snapshotted checklist items, plus scan metadata on existing application documents. The department catalogue reuses core document types and requirements. Existing case snapshots remain stable after later catalogue edits.
- Added authorised catalogue configuration with source reference and version checks. Only authorised senior building officers/system administrators may publish. No department checklist content, application records or user accounts were seeded.
- Added private content-validated, SHA-256 document revisions through ApplicationDocumentService and the DocumentScanner contract. Replacements preserve old revisions and reset verification; failed metadata writes remove the new blob and roll back the old current revision. The ClamAV adapter refuses detections, unavailable scanning, errors and timeouts.
- Added granular upload/verify/configure permissions and configured workflow guards for opening a checklist, returning for correction, resubmitting and completing document verification. Required current revisions must be accepted before fee assessment; optional supplied documents must be reviewed or explicitly waived. State changes and document mutations preserve actor audit and row-version checks.
- Added protected downloads with department/type/district, case ownership and checksum enforcement, including historical revisions. Added operational document/review and catalogue screens using the existing Blade shell/classes, with mobile layout checks and working revision history.
- Installed official portable ClamAV 1.5.4 under ignored `.tools/clamav/`. Verified the archive's detached signature against the key obtained from the official Talos download page, downloaded/verified official signature databases through FreshClam, and successfully scanned a generated safe PDF through the actual application adapter. Local executable/database/certificate paths are in untracked `.env`; committed configuration defaults to Linux `clamscan`. No scan bypass or recurring automation was added.
- Applied only the additive migration and dedicated BangunanDocumentWorkflowSeeder to the verified local development database. Existing application count was unchanged. Added ADR-310 and `docs/runbooks/bangunan-document-verification.md` with operating, deployment, signature maintenance and recovery instructions.

Verification: **114 tests, 790 assertions passed** (22 new document-verification cases); guarded PostgreSQL migration/rollback/re-migration passed; Pint and `git diff --check` passed. Scanner adapter tests cover clean/detected/error/missing-process outcomes, with explicit test doubles confined to tests. Browser checks used actual Blade responses from disposable test records, including catalogue row addition, revision expansion and a mobile width check (content 375px / viewport 375px). A final Blade directive error was corrected and the full suite rerun successfully.

Remaining: authorised department officers must enter the confirmed family checklists before operational verification can start. Next development increment is fee-rule configuration and fee assessment, followed by later guarded stages. No payment, approval, production cutover or hosted Linux CI result is claimed. Existing PKM behavior remains for Phase 2; its historical upload service has not yet moved onto the new scanner-backed core service. All changes remain local pending pull request and hosted CI.

## Operational Bangunan registration and shared workflow (ADR-309)

Implemented 2026-10-27 in the existing iNDECS Laravel application:

- Added authenticated `/bangunan` list, search/type/status filters, scoped metrics, registration and case-detail routes for PB, PBK, PBS, PMT and TEL. Reused the shared Blade shell, CSS classes, tokens, theme controls and existing core records. No prototype mode or sample application data was added to the local development database.
- Stored department fields in `bangunan.app_pb_applications`; core applications, parties, sites, lots, references and actor-attributed audit/history are created atomically. PB requires an OSC reference. Department, type and district constraints apply to lists, forms, details and transitions. New roles/accounts were not created or promoted.
- Added shared ApplicationReferenceGenerator, retaining PkmReferenceGenerator as a compatible wrapper. Added versioned core workflow templates/transitions and a pinned application template. WorkflowService locks records and validates permissions, source state and expected row version before appending history and audit. The first configured transition starts document verification; existing PKM workflow remains unchanged.
- Explicitly rejected cross-module application IDs in PKM controllers after broadening the shared application access method. Added cross-module regression coverage, including administrators.
- Corrected shared lot-repeater indexes after row removal/addition and shared mobile-header overflow. Reused record-side layout; did not introduce parallel UI components.
- Aligned canonical programme roadmap/decision records and DESIGN-DIRECTION with the accepted order: module rollout 1 Bangunan, 2 PKM. Fixed the Ubuntu CI migration-check step to use its PostgreSQL port 5432.
- Added deployment/operation notes in `docs/runbooks/bangunan-registration.md` and ADR-309 before implementation.

Verification:

- Full PostgreSQL suite passed: **92 tests, 616 assertions** (18 new Bangunan cases).
- Guarded migration/rollback/re-migration passed on disposable `indecs_test`.
- Changed-file Pint and `git diff --check` passed.
- Actual Blade responses from test requests checked in browser at desktop and mobile widths. Mobile DOM width matched viewport (375px) after the header fix. Adding, removing and re-adding lots retained unique sequential field indexes. Test-view capture is optional and stored only under ignored `.tools/bangunan-ui/`.
- Applied only the additive migration and dedicated BangunanWorkflowSeeder to the verified loopback local iNDECS database; existing application count unchanged. No full reseed or application/user test data was inserted there.
- Started the real local application at http://127.0.0.1:8000/bangunan; normal staff authentication remains required. The source is intended for Linux deployment and Ubuntu CI; this session's executable tests ran on the current Windows workstation with PostgreSQL. Hosted Linux CI has not been run for this increment.

Remaining work: family-specific document requirements/storage and verification completion, then fee assessment and later guarded transitions. Legacy lookup remains separate; no operational legacy cutover, permit/approval issuance, AIRS invocation or production deployment is claimed. Changes remain local and require a pull request and hosted green CI before merge.

## iNDECS Bangunan Read-Only Legacy Lookup Slice (ADR-308)

- Added [ADR-308](docs/adr/ADR-308-bangunan-legacy-readonly-lookup.md) and
  migration `2026_09_21_000900_create_bangunan_legacy_lookup_tables.php`:
  - Provisioned PostgreSQL schema `bangunan` and read-only legacy lookup tables
    `bangunan.legacy_permohonan` (`PERMOHONAN1`), `bangunan.legacy_perunding`
    (`PERUNDING`), `bangunan.legacy_bangunan` (`BANGUNAN1`), and
    `bangunan.legacy_ccc` (`CCC`) with `pg_trgm` GIN indexes on file reference,
    serial number, applicant, lot, and location.
  - Updated `config/database.php` `pgsql.search_path` to `'public,core,bangunan'`
    so schema rebuilds and migrations manage `bangunan` tables cleanly.
- Seeded the five `JBANG` application family codes (`PB`, `PBK`, `PBS`, `PMT`,
  `TEL`), `JBANG` roles (`building_officer`, `senior_building_officer`), and
  permission `bangunan.legacy.view` in `DatabaseSeeder`.
- Added read-only Eloquent models (`LegacyPermohonan`, `LegacyPerunding`,
  `LegacyBangunan`, `LegacyCcc`) that block all Eloquent writes/deletes and
  evaluate effective legacy status using the verified `frmMain.fetch` branch-date
  hierarchy (`PmnTkhTolak`, `PmnTkhBatal`, `PmnTkhTarikBalikR`, `PmnTkhLulus`,
  `PmnTkhTangguh` ahead of `PmnStatus`).
- Added `BangunanLegacySyncService` and console command
  `php artisan bangunan:sync-legacy` to load scrubbed legacy snapshots and print
  reconciliation counts.
- Added `EnsureBangunanLegacyAccess` (`bangunan.legacy` middleware),
  `BangunanLegacyController`, and Bahasa Melayu Blade views (`bangunan/legacy/index`
  and `bangunan/legacy/show`) under `/bangunan/legacy`, including shared
  `PmnNorujukan` sibling lineage and `CCC` multi-parent (`needs_review`) warnings.
- Added `tests/Feature/BangunanLegacyLookupTest.php`.
- Verification: `php scripts/verify-database.php` passed; `php artisan test`
  passed (**74 tests, 405 assertions**); `php vendor/bin/pint --test` passed.

## Append-Only Audit/History and District/Action RBAC Increment (ADR-306, ADR-307)

- Added [ADR-306](docs/adr/ADR-306-append-only-audit-and-history.md) and
  migration `2026_09_21_000700_enforce_append_only_audit_and_history.php`:
  - Replaced `stage_histories.application_id`, `stage_histories.actor_id` and
    `audit_logs.actor_id` foreign keys with `ON DELETE RESTRICT` so applications
    with history and users referenced as historical actors cannot be deleted or
    have their attribution nulled out.
  - Added PostgreSQL trigger function `core.prevent_history_mutation()` and
    `BEFORE UPDATE OR DELETE` triggers on `audit_logs` and `stage_histories`
    (raising SQLSTATE `42501`), plus model-level `updating`/`deleting` guards on
    `AuditLog` and `StageHistory`.
- Added [ADR-307](docs/adr/ADR-307-district-scoping-and-action-permissions.md)
  and migration `2026_09_21_000800_add_district_scope_and_action_permissions.php`:
  - Added nullable `users.district` column for district (`daerah`) scoping.
  - Seeded granular PKM permissions (`perancang.pkm.access`,
    `perancang.pkm.register`, `perancang.pkm.review`) and separated
    `intake_officer` (registration/document upload only) from
    `planning_officer` / `senior_planning_officer` (completeness review
    decisions).
  - Added `EnsurePkmPermission` route middleware (`perancang.permission`),
    `User::canPerformPkm()`, `User::canAccessApplication()` and
    `Application::scopeVisibleTo()` across `DashboardController` and
    `PkmApplicationController`.
- Added feature tests in `tests/Feature/AppendOnlyHistoryTest.php` and extended
  `tests/Feature/CoreRbacTest.php`.
- Verification: `php scripts/verify-database.php` (including full rollback and
  re-migrate of the triggers and foreign keys) passed; `php artisan test` passed
  (**70 tests, 367 assertions**); `php vendor/bin/pint --test` passed.

## Consolidated Local PostgreSQL 18 Migration (iNDECS, AIRS, MIDR)

- Migrated all data from the Docker PostgreSQL containers (`indecs_postgres` on
  `5432` and `midr-postgis` on `5433`) into the native Windows PostgreSQL 18 +
  PostGIS instance (`postgresql-x64-18` on `127.0.0.1:5434`, database `iNDECS`):
  - `iNDECS` tables in `public` and `core` (9 migrations, seeded roles/permissions,
    departments, application types, document requirements, users including linked
    staff `18811`, audit logs).
  - `AIRS` (`Plan Review`) tables in schema `airs` (`search_path=airs,public`,
    14 migrations, 3 review modules).
  - `MIDR` tables, views and functions in schemas `catalogue`, `core`,
    `application` and `publish` plus `public.midr_schema_migration` (13 migrations,
    5,137 applications, 12,983 lots, 6,952 events, 4,082 geometries).
- Provisioned isolated test databases `indecs_test` and `airs_test` on
  `127.0.0.1:5434` with `postgis`, `pg_trgm` and `btree_gist`.
- Disabled auto-restart and stopped the Docker containers (`indecs_postgres`,
  `midr-postgis`, `indecs-mailpit-1`) and stopped Docker Desktop.
- Updated database configurations and documentation across `iNDECS`, `Plan Review`
  (`AIRS`), and `MIDR` to target `127.0.0.1:5434` (`DB_DATABASE=iNDECS`).
- Verification on native PostgreSQL 18 (`127.0.0.1:5434`) with Docker stopped:
  - `iNDECS`: `php scripts/verify-database.php` passed; `php artisan test` passed
    (**64 tests, 337 assertions**).
  - `AIRS`: `php artisan migrate:status` verified all 14 migrations `[1] Ran` in
    schema `airs` of database `iNDECS`.
  - `MIDR`: `python -m scripts.db.manage test` passed (**12/12 SQL tests**);
    `python -m scripts.domains.verify` passed; `python -m scripts.applications.verify_load`
    passed 100% including byte-exact UTF-8 raw payload checks.

## Corrected Credential Accepted for 18811

- User corrected the previously supplied password. Repeated the live
  `staff:check-login 18811` check outside the restricted network environment,
  entering the corrected credential through the hidden prompt.
- MBSP accepted the staff number and corrected password; command exited 0.
  The earlier credential rejection was resolved by this correction.
- No password or password hash is stored in these notes. Local account linkage
  and PKM access were already verified. Browser sign-in with the corrected
  password is the remaining end-to-end confirmation.

## Direct Credential Check for 18811

- At the user's request, ran `php artisan staff:check-login 18811` outside
  the restricted network environment, entering the supplied password through
  the command's hidden prompt. No credential was added to project files.
- The authenticator returned a rejection (command exit code 1), rather than a
  connection or configuration error. This reproduces the browser failure using
  the configured MBSP adapter independently of browser sessions and local RBAC.
- Successful authentication remains blocked. An independent Postman check with
  the same staff credentials can distinguish an upstream credential/account issue
  from an unresolved adapter difference; the prior sample success for `40004`
  does not validate `18811`.

## Distinguish MBSP Rejection From Local Access Denial

- Rechecked `18811`: local account exists, is active, and has PKM access.
  Configured password transport remains MD5. Compared the Billing System adapter;
  ordinary password transport uses the same form fields and MD5 transformation.
- Replaced the combined staff-login failure message with separate MBSP rejection
  and local access-denial messages. Local access details appear only after MBSP
  accepts the credentials; rejected credentials do not disclose account existence.
- Regression tests cover both denial paths, including identical MBSP rejection
  messages for linked and unlinked accounts. Focused suite passed: 16 tests,
  93 assertions. Pint passed.
- This improves diagnosis; it does not demonstrate successful authentication of
  `18811`. The next real-password attempt will identify which gate rejects login.

## Test Identity Correction

- User confirmed that staff number `40004` and its successful Postman response
  are for testing only; they are not the user's identity.
- Corrected the earlier mistaken reassignment by running the audited
  `staff:approve` command to restore `mohammadasyraf@mbsp.gov.my` to `18811`.
- The sample success response demonstrates the API response format and acceptance
  of the test credentials only. It does not validate credentials for `18811`.
- Real-account authentication for `18811` remains unverified after this correction.
  The browser must receive that staff member's actual MBSP password.

## Login Recheck After Recurring Failure

- Recent local logs show MBSP connection exceptions. The credential-free
  diagnostic fails inside the restricted agent environment, but succeeds outside
  it with verified HTTPS and HTTP 200.
- Started a separate hidden Laravel development server outside the restricted
  environment at `http://127.0.0.1:8001/login`, leaving port 8000 untouched.
- Tested the actual login POST with CSRF/session cookies and deliberately invalid
  synthetic credentials on both ports 8000 and 8001. Both returned the controlled
  credential-rejection message, without the service-unavailable message.
  This verifies transport and error handling, not successful staff authentication.
- No authentication bypass, password-mode change, or account reassignment made.
- Correction to the earlier account-alignment assumption: Postman's sample
  number `40004` does not establish that it belongs to the user originally asking
  about `18811`. Confirm the intended number before changing the existing link.
- Asked the user for the intended staff number and current exact browser error;
  no password requested. Successful real-account browser login remains unverified.


## MBSP Header Alignment

- Reviewed the user's Postman header screenshot. The significant headers are
  Basic `Authorization` and `Content-Type: application/x-www-form-urlencoded`;
  `Host`, `Content-Length`, `Connection`, and Postman-specific headers are
  generated by the HTTP client/runtime.
- Updated the MBSP staff authenticator to send `Accept: */*`, matching Postman's
  neutral accept header instead of forcing `application/json`. The adapter still
  parses the documented JSON response and still uses Basic Auth plus
  x-www-form-urlencoded body fields.
- Updated the MBSP staff-login regression test to assert the `Accept: */*`
  header alongside Basic Auth, form encoding, dynamic API-token field, `nostaff`,
  and password transport.
- Verification: a first test attempt was invalid because focused and full tests
  were accidentally run in parallel against the same PostgreSQL `indecs_test`
  database, causing table rebuild collisions. After the guarded database reset,
  `MbspStaffLoginTest` passed, full suite passed: **63 tests, 331 assertions**;
  Pint passed; `git diff --check` passed.

## Postman MBSP Credential Clarification

- Reviewed the user's Postman screenshots. The screenshots show the MBSP API
  request pattern: Basic Auth, form-encoded body, dynamic API-token field,
  `nostaff`, and an already-MD5-hashed `password` value.
- Confirmed the Postman credential shown is for staff number `40004`, not
  `18811`. The MD5 value in the screenshot is also not the MD5 of the earlier
  password text supplied for `18811`.
- Added `staff:check-login --prehashed-md5` for diagnostic checks against
  Postman-style MD5 values through a hidden prompt. This helper sends the
  prompted MD5 value as-is, but the web login still expects the user's real MBSP
  password and hashes it before calling the API.
- Live diagnostic result: MBSP accepted staff number `40004` with the Postman
  MD5 value supplied in the screenshot.
- Local iNDECS account alignment: moved `mohammadasyraf@mbsp.gov.my` from
  staff number `18811` to `40004`, department `JPBD`, role `planning_officer`,
  active staff account, `has_password=false`, `canAccessPkm=true`.
- Staff number `18811` is no longer linked locally.
- Important login implication: to sign in through the browser, the staff member
  must type the real MBSP password whose MD5 matches the accepted Postman value.
  Typing the MD5 hash into the browser will be hashed again and rejected.
- Verification: **63 tests, 331 assertions passed**; Pint passed; `git diff
  --check` passed; `/login` returns HTTP 200.

## Local Staff Account Approval for 18811

- Explained the `staff:link mohammadasyraf\@mbsp.gov.my 18811` failure:
  PowerShell does not require escaping `@`, so the backslash becomes part of the
  email, and no local account existed for either `mohammadasyraf@mbsp.gov.my` or
  `mohammadasyraf\@mbsp.gov.my`.
- Improved `staff:link` so it reports exact failure reasons: missing account,
  invalid PowerShell-style backslash email, inactive/unauthorised account,
  conflicting existing account link or conflicting staff-number link.
- Added `staff:approve {email} {staff_number}` for explicit creation/approval of
  local staff shell accounts. It creates a staff account with no usable local
  password, validates department/role PKM permission first, links the staff
  number, and writes an audit log.
- Reran the local seeder to populate the new core RBAC lookup rows in the local
  application database.
- Created and linked the local staff shell account:
  `mohammadasyraf@mbsp.gov.my`, staff number `18811`, department `JPBD`, role
  `planning_officer`, active staff account, `has_password=false`.
- Verified the linked account returns `canAccessPkm=true`.
- Important remaining blocker: the MBSP API still rejected the supplied
  staff-number/password pair for `18811`; local linking is now ready, but actual
  login still requires MBSP to accept the credential.
- Verification: **61 tests, 325 assertions passed**; Pint passed.

## Staff Account 18811 Check

- Checked staff number `18811` after the user supplied the password separately.
  The password was entered only into an interactive hidden prompt and was not
  written to tracked files or progress notes.
- iNDECS currently has **no local user linked** to `staff_number = 18811`.
  Count in the local users table is `0`, so a successful MBSP credential check
  would still require an approved local iNDECS account to be linked before login.
- Found the real cause of the earlier MBSP availability warning: PHP/cURL had no
  local CA file configured and could not verify the MBSP HTTPS certificate chain
  (`unable to get local issuer certificate`). TCP was reachable, but TLS
  verification failed.
- Added `MBSP_STAFF_API_CA_BUNDLE` support to the MBSP adapter and diagnostic
  command. Local `.env` points to Git's installed CA bundle on this machine.
  `.env.example` documents the setting without a machine-specific value.
- Updated `staff:diagnose-login` to validate the CA bundle and perform a
  credential-free HTTPS check. It now reports HTTP 200 for the configured MBSP
  endpoint.
- Added `staff:check-login {staff_number}` for live credential checks through a
  hidden password prompt. It does not create users, link accounts or print
  credentials.
- Live result for staff number `18811`: MBSP rejected the staff number/password
  pair provided in this session. This is distinct from the local-link issue.
- Restarted the local Laravel dev server at `http://127.0.0.1:8000` after the
  CA-bundle change. `/login` returns HTTP 200.
- Verification: **58 tests, 310 assertions passed**; Pint passed.

## MBSP Login Availability Fix

- Investigated the browser warning "Perkhidmatan log masuk MBSP tidak tersedia.
  Cuba semula kemudian." The MBSP staff login configuration keys are present, so
  this was not a missing-configuration case.
- Added safe warning logs inside `MbspStaffAuthenticator` for unreachable,
  non-success and malformed MBSP responses. Logs record only status/reason
  context, not staff passwords, API tokens or response payloads.
- Added `php artisan staff:diagnose-login`, which validates the MBSP staff-login
  configuration shape and checks TCP reachability to the configured HTTPS host
  without sending a staff number, staff password or API form payload.
- Diagnostic result inside the restricted sandbox: endpoint was not reachable.
  Diagnostic result outside the sandbox: endpoint TCP port is reachable. The
  browser warning was therefore caused by the Laravel dev server running in the
  restricted sandbox, where outbound MBSP API calls are blocked.
- Restarted the local Laravel dev server outside the sandbox at
  `http://127.0.0.1:8000`; `/login` returns HTTP 200 and shows the staff-number
  login form.
- Verification: **58 tests, 310 assertions passed**; `MbspStaffLoginTest` passed;
  Pint passed for the diagnostic/logging changes.

## Core RBAC Increment

- Continued Phase A foundation after rechecking this progress file, repository
  status and the current test suite.
- Added ADR-305 for the first core RBAC permission registry. This records the
  decision to keep `users.role` as the current assigned role code while moving
  permission authority into scoped `core` tables.
- Added `core.roles`, `core.permissions` and `core.role_permissions`, scoped by
  department and application type with null scope values reserved for global
  grants such as `system_admin`.
- Seeded the current Perancang staff roles and `perancang.pkm.access`
  permission. Existing PKM demo/staff behavior is preserved through data rather
  than a hard-coded role list.
- Switched `User::canAccessPkm()` to use `CorePermissionService`; public and
  guest accounts are still rejected before RBAC lookup, so public login methods
  cannot gain staff access by changing request data or role values.
- Added RBAC regression tests for seeded access, removed-grant denial and
  department scope, including the global system-admin grant.
- Applied the additive RBAC migration to the local application database.
- Verification: **58 tests, 310 assertions passed**; full PostgreSQL migration/
  seed/rollback/re-migrate verification passed; Pint passed; `git diff --check`
  passed.

## Public Access Increment

- Implemented the user's separation: staff/officers use MBSP login; public users
  use registered email/password, Google, or email-only access without registration.
- Added ADR-304, explicit staff/public/guest account types, and public role checks.
  Public identities cannot enter Perancang or acquire staff permissions through
  registration, Google, password reset or an email link.
- Installed Laravel Socialite 5.31 for stateful Google OAuth. Only verified Google
  emails are accepted; subjects identify accounts. Existing email accounts require
  explicit linking after local/email login. Google tokens are not stored.
- Implemented registration verification, password reset, one-time email links,
  optional guest-to-registered account claiming, and logout. Tokens are hashed,
  purpose-bound, expire after 15 minutes and are consumed on a CSRF-protected POST.
  GET confirmation pages do not consume links. Public passwords require 12 characters.
- Added `core.public_access_tokens`; search_path is public,core so existing tables
  stay in public while schema-qualified identity tokens participate in test rebuilds.
- Added `/portal` public entry/account pages and a staff-login cross-link using
  existing shared CSS tokens. The inherited token palette still differs from the
  documented Calm Intelligence palette; a global theme reconciliation is outstanding.
- Added loopback-only Mailpit to Compose. Local `.env` now uses SMTP port 1025 and
  APP_URL `http://localhost:8000`; email is captured at `http://localhost:8025`.
- Prepared `docs/runbooks/google-and-public-access.md` with exact Google External
  Web client settings, callback URLs, scopes and production SMTP requirements.
- Applied the additive public identity migration to the local application database.
- Verification: **55 tests, 304 assertions passed**; full migration/seed/rollback/
  re-migrate passed on PostgreSQL; Pint/diff checks passed; Composer audit clean.
  Headless Edge/Playwright screenshots checked login, registration, email access
  and password recovery at 1366px and 390px, with HTTP 200 and no horizontal overflow.
- Local server is running at `http://localhost:8000/portal/login` (started PHP
  process ID 27820). Browser-check scripts/screenshots are under ignored `.tools/`.

### Public Access Limitations

- Google client ID/secret must be configured by the user; live OAuth is unverified.
- Production SMTP is not configured. Automated tests fake notification delivery;
  Mailpit is for local testing only, not delivery to real recipients.
- **Public business submission forms, submission receipts and case-correspondence
  emails are not implemented.** This increment establishes access and verified
  email identities for those workflows. The portal explicitly reports submissions
  are not open; it does not expose officer forms as a substitute.
- Phone OTP, public application ownership/authorisation, shared OIDC and remaining
  Phase A controls are still outstanding. No external accounts or Google project
  settings were changed; no commit, push or PR was created.

## MBSP Staff Authentication Increment

- Read the user-supplied `API LOGIN STAFF (1).pdf` and Billing System's
  `StaffDirectoryService.php`, `Auth.php` and staff-directory configuration.
- Added ADR-303, `StaffAuthenticator` and the MBSP HTTP adapter: HTTPS, Basic Auth,
  form-encoded dynamic API-key field, nostaff and MD5 password transport matching
  Billing System. Raw mode is explicitly configurable. Credentials are not logged.
- Login now defaults to MBSP staff number/password. Local demo login requires an
  explicit local driver and local/testing environment; there is no API-failure fallback.
- Expired passwords, mismatched identities, invalid responses and outages are denied.
  Existing active-user and module permissions remain required. No automatic email
  linking, user creation or role assignment was introduced.
- Added a nullable unique users.staff_number migration and `staff:link` command
  for audited linking of existing approved accounts. Conflicting links are refused.
- Configured the provided service settings in ignored local `.env` only; tracked
  `.env.example` contains empty placeholders. No staff password was supplied or stored.
- Updated the login form and added `docs/runbooks/mbsp-staff-login.md`, including
  Postman request instructions without embedded credentials.
- Applied the additive migration to the local application database and verified
  migration/seed/rollback/re-migrate on the disposable test database.
- Latest verification: **39 tests, 202 assertions passed**; Pint and diff checks
  passed. API tests use HTTP fakes and prevent stray requests.
- Live sign-in remains unverified: an authorised staff number must be linked to
  the intended existing iNDECS account. No sample PDF identity was linked, and no
  live staff password was requested or transmitted. Shared OIDC remains pending.

## Current Status

The repository readiness review and the first local Phase A foundation increment
are complete. PostgreSQL setup and migration verification now work, and the
registration audit and login-throttling gaps are fixed. Programme Phase A remains
in progress; Bangunan workflow implementation has not started.

## Latest Implementation (2026-09-21)

- Recorded implementation decisions in `docs/adr/ADR-301-postgresql-verification.md`
  and `docs/adr/ADR-302-login-throttling-and-registration-actors.md` before code.
- Removed the SQLite connection/default and Composer SQLite creation hook.
- Made PostgreSQL database/extension setup idempotent, including `indecs_test`,
  and restricted the published database port to loopback.
- Added `tests/Support/TestDatabaseSafety.php`, checked before feature-test database
  refresh. It rejects non-testing environments, non-PostgreSQL targets, other
  database names, URL overrides to unsafe targets and alternate read/write targets.
  The actual server database is checked before destructive verification.
- Added `scripts/verify-database.php` for required-extension checks and a guarded
  fresh migrate/seed, rollback and re-migrate/seed on disposable `indecs_test`.
- Added `.github/workflows/verify.yml` with dependency installation/audits,
  formatting, PostgreSQL migration/tests and frontend build. This is a local
  workflow definition; no hosted run or branch protection is claimed.
- Added `docs/runbooks/local-development.md` and updated the README setup link.
- Added a five-failure, 60-second local-login limiter using a hashed normalised
  email/IP key; successful login clears prior failures. Added regression tests.
- Registration now requires the authenticated User actor and records that officer
  on both its stage-history and audit rows in the existing transaction.
- Started Docker Desktop and the existing PostgreSQL Compose service. The existing
  volume was retained. Only the disposable test database was rebuilt for tests.

## Latest Verification

| Check | Result |
|---|---|
| Docker Compose build/start | Passed; PostgreSQL healthy, loopback port binding. |
| Idempotent setup on existing volume | Passed; existing extensions preserved. |
| Guarded PostgreSQL migration verification | Passed; extensions present, migrate/seed/rollback/re-migrate/seed succeeded. |
| `php artisan test` | Passed: 29 tests, 136 assertions, including safety guard, login limit and actor attribution. |
| `php vendor/bin/pint --test` | Passed after implementation. |
| `composer validate --no-check-publish` | Passed. |
| Composer and npm dependency audits | Passed; no known vulnerability advisories reported. |
| Frontend production build | Passed using the installed npm CLI directly; normal npm wrapper has a missing roaming npm path. Optional fontaine warning remains. |
| `git diff --check` | Passed. |

The Docker/database blocker from the initial review is resolved. The npm wrapper
issue is machine-specific: `node 'C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js' run build`
worked. No global npm installation was changed. No commit, push or PR was created.

## Completed

- Read the iNDECS instructions, design direction and Bangunan programme documents,
  plus relevant legacy documentation, current proposals and mockups in
  `C:\Users\Asyra\Documents\eBangunan` during the earlier documentation review.
- Rechecked the current repository against Phase A requirements on 2026-09-21.
- Confirmed existing Git history, PostgreSQL development configuration,
  `/perancang` routes and `app_pkm_*` naming. These were existing changes, not
  implemented during this review.
- Inspected registration, completeness workflow, document handling, authentication,
  access checks, migrations, tests and local setup configuration.
- Created [the repository readiness report](docs/bangunan/18-repository-readiness.md)
  with prioritised findings, evidence, acceptance criteria and implementation order.
- Created this progress file at the user's request.

## Original Review Findings (Historical)

- Login lacks application-level failed-attempt throttling.
- Registration omits the authenticated officer from its audit and stage-history rows.
- Audit/history writes exist, but database-enforced append-only protection is absent;
  stage history can be removed by cascading application deletion.
- Permissions remain a hard-coded Perancang role/department check, without the
  planned per-action permissions and district scoping.
- PostgreSQL setup retains a SQLite fallback and creation hook. The dedicated test
  database still needs manual provisioning, and tables remain in the public schema.
- No tracked CI configuration or configured Git remote was found.
- Shared identity, additional local services, staging and restore evidence remain
  outstanding or unverified. AIRS runtime readiness was not assessed in this review.

## Initial Review Verification (Before Implementation)

| Check | Result |
|---|---|
| `php vendor/bin/pint --test` | Passed. |
| `php artisan route:list --except-vendor` | Passed; 15 application routes loaded. |
| PHP PostgreSQL support | `pdo_pgsql` and `pgsql` available in PHP 8.5.7. |
| Docker service status | Engine pipe unavailable, also after retry outside the sandbox. |
| PostgreSQL TCP endpoint | `127.0.0.1:5432` unavailable. |
| PostgreSQL feature tests / migration round-trip | Not run because the database was unavailable. |

No application code or database data was changed during the readiness review.
No commit, push or pull request was created.

## Next Work

1. Add Larastan static analysis and a deployable application image with scanning;
   connect the workflow to the approved host and verify green hosted CI.
2. Plan data-preserving schema migration and separate runtime/migration database
   roles; enforce append-only audit/history at the database boundary.
3. Add shared RBAC, department/district/type scoping and staff identity integration,
   including verified OIDC capability and AIRS coordination.
4. Complete the staff session baseline and local Redis/object storage/mail setup.
5. Prepare legacy mapping and read-only ingestion with authorised sample data.
6. Complete staging, a recorded restore drill, onboarding by a non-author and
   the ICT handover pack. Rehearse fresh-machine setup independently.

## Outstanding Dependencies

- ICT repository-host selection is needed for hosted CI and branch protection.
- Staff authentication access, authorised legacy data access and infrastructure
  ownership need coordination with the relevant programme/ICT owners.

Update this file after each implementation increment with changed files,
verification results and remaining work. Do not mark programme phases complete
based solely on documentation or unexecuted test configuration.

## Approved workspace redesign — 2026-10-28

The programme owner approved the navy-sidebar concept and authorised combining
this work with the existing Bangunan registration/document increments in one PR.

- Google Sans retained across shared tokens and the optional Vite stylesheet;
  removed the superseded Archivo/Instrument Sans setup and accent picker.
- Applied the navy/teal theme to staff/public identity, Perancang, operational
  Bangunan, document catalogues/revisions/verification and legacy lookups.
- Added local Lucide icons, recurring iNDECS marks, finite motion with user/OS
  reduction controls, permission-filtered department links and record navigation.
- Added AMIRA's keyboard-accessible context rail/dialog. Guidance is explicitly
  local; a live AIRS assistant connection remains unavailable. No external AI
  calls or assistant-driven business writes were introduced.
- Added ADR-311 and the theme/AMIRA runbook; retained existing Bangunan business
  rules, scopes, document ownership checks and append-only history.
- Cleared the pre-existing security audit failures with compatible lockfile
  updates: five Composer package updates and npm's supported audit fixes.

Verification on the final dependency set: 119 PostgreSQL tests / 849 assertions
passed; Pint, compiled Blade views, JavaScript syntax, Composer validation and
production build passed. Composer/npm report no security advisories. The guarded
PostgreSQL migration/seed/rollback/re-migrate check passed on indecs_test. Browser
review covered light/dark Bangunan lists/cases/documents, mobile forms/drawers,
AMIRA context and Escape/focus return, lot repeaters, public registration and
staff login. Google Sans loaded successfully. The normal application database
was inspected read-only and has no pending migrations; no application records
were migrated for the redesign.

Remaining capability: implement the authenticated AIRS assistant API separately;
AMIRA's local-guidance availability notice must stay until then. Fees, payments
and later Bangunan stages retain their existing roadmap scope. Hosted CI and PR
status are recorded in the linked review rather than inferred from local checks.
