# 18. Repository Readiness Review

Date: 2026-09-21. Baseline: commit `2cceff7`.

This completes step 1 of the proposed preparation sequence: check the repository
against the agreed architecture. It does not certify completion of programme
Phase A. Findings concern this iNDECS checkout; AIRS and deployed environments
were not independently assessed in this review.

## Outcome

The PKM registration and completeness implementation provides a useful starting
point. Continue with foundation work before adding Bangunan application workflows.
PostgreSQL configuration and department naming have already progressed beyond the
initial programme assessment, but database isolation, CI, identity, permissions
and audit enforcement remain incomplete.

## Prioritised Findings

Priority here means delivery order and impact on readiness, not a formal security rating.

| Priority | Finding and evidence | Required outcome |
|---|---|---|
| High | Login calls `Auth::attempt()` without a rate limiter in `app/Http/Requests/LoginRequest.php:25`; `routes/web.php` adds no login throttle. Repeated failed attempts are unrestricted by the application code inspected. | Enforce the documented five-failure limit, with recovery and tests for failed and successful authentication. |
| High | Registration does not accept an actor and omits `actor_id` on both history and audit inserts (`app/Services/PkmRegistrationService.php:20`). | Every officer registration records the authenticated actor in the same transaction as the application and history. |
| High | Audit records are ordinary writable rows; `AuditLog::UPDATED_AT = null` only disables the update timestamp. Stage history cascades on application deletion, and actor references are nulled on user deletion (`database/migrations/2026_07_14_000100_create_skpb_core_tables.php:154`). | Specify retention behaviour, separate runtime and migration privileges, enforce append-only audit/history rules in PostgreSQL, and verify them using the runtime role. |
| High | Access is one hard-coded department/role gate (`app/Models/User.php:26`), shared by all Perancang actions. No district scope or per-action permission model is implemented. | Add shared permissions and department/district/type scoping, including denied-access tests, before Bangunan records become accessible. |
| High | No tracked CI configuration or configured Git remote was found. PostgreSQL feature tests cannot be verified in the current environment. | Establish reproducible test setup and the agreed PR checks; connect to the ICT-approved repository host and enforce merge checks there. |
| Medium | PostgreSQL is selected in `.env.example` and `phpunit.xml`, but `config/database.php:20` still defaults to SQLite and `composer.json:63` still creates a SQLite file. | Remove the SQLite application path and creation hook; make PostgreSQL the default. |
| Medium | PostgreSQL search path remains `public` (`config/database.php:98`); migrations create unqualified tables. The agreed seven schemas are not provisioned by repository scripts. | Write an ADR and a reversible migration plan for existing tables, model mappings, foreign keys and test assertions. Preserve current data. |
| Medium | Compose provides only PostgreSQL. Initialisation provisions `indecs` and `airs` extensions but not `indecs_test`; tests require manual setup. PHP Redis support is also absent from the inspected CLI runtime. | Automate dedicated test database provisioning; add Redis, object storage and local mail with compatible PHP clients as those capabilities are introduced. |
| Medium | `.env.example` and session defaults use database sessions with a 120-minute lifetime. Document uploads use private local storage and Laravel MIME validation, without the planned malware scan pipeline. | Deliver the staff session baseline and scanned object-storage workflow before operational exposure. |

The Compose database administrator is also the username supplied in the example
application/test configuration. This is development configuration, but it cannot
serve as the runtime privilege model for append-only auditing. The published
database port is not limited to loopback in the Compose file.

## Existing Work To Preserve

- Git history exists; the working tree was clean at review start. `.env` is not
  tracked in the current checkout. Historical secret exposure and rotation were
  not audited, so those programme checklist items remain unverified.
- `/perancang` routes and `app_pkm_*` extension-table names are already present.
- Shared applications, parties, submission snapshots, sites and lots are separated
  from PKM-specific fields.
- Registration, workflow transitions and document revision writes use transactions.
  Reference allocation and workflow operations use row locks.
- Workflow transitions write stage history and audit records. These are useful
  existing behaviours to retain while adding stronger database enforcement.
- The feature tests cover registration, reference sequencing, completeness,
  document revisions, amendment cycles, guest redirection and successful login.
  They do not yet establish district isolation, action-specific permissions,
  login throttling or database-enforced audit immutability.

## Verification

| Check | Result |
|---|---|
| `php vendor/bin/pint --test` | Passed. |
| `php artisan route:list --except-vendor` | Passed; 15 application routes loaded. |
| PHP runtime | PHP 8.5.7 with `pdo_pgsql`, `pgsql`, fileinfo and required common extensions available. |
| `docker compose ps` | Failed: Docker engine pipe unavailable, including when retried outside the sandbox. |
| TCP connection to `127.0.0.1:5432` | Unavailable. |
| PostgreSQL feature suite and migration round-trip | Not run: database unavailable. No claim of passing database tests. |
| Deployed extensions, schemas, backup restore and CI status | Not verified. |

No application data was changed. This review adds documentation only.

## First Implementation Scope

The next implementation increment is **reproducible PostgreSQL development and
verification**, under ADR-202 and ADR-207. Write the implementation ADR before
changing configuration or code.

1. Remove SQLite fallback and creation hooks; retain SQL Server only as needed
   for the future read-only legacy adapter.
2. Provision `indecs_test` and its three required extensions automatically on a
   fresh environment. Provide an idempotent setup path for existing volumes.
   Do not delete volumes to rerun initialisation.
3. Add a test bootstrap guard that refuses destructive database tests when the
   resolved connection targets the application database, including URL overrides.
4. Limit the local database port to loopback and document local-only credentials.
5. Add the agreed CI checks on the selected host: formatting, static analysis,
   dependency audits, PostgreSQL tests, migration/seed/rollback/re-migrate and
   build checks. Image scanning follows the deployable application image.
6. Update the onboarding runbook with exact setup, verification and recovery
   commands. Record actual results against a disposable test database.

Acceptance: a clean checkout can provision the development/test databases without
manual SQL; required extensions are present; the existing PKM suite passes on
PostgreSQL; migrations round-trip on an isolated database; no test command can
silently fall back to SQLite or target the normal application database.

## Following Increments

| Order | Scope | Completion evidence |
|---|---|---|
| 2 | Login throttling, registration actor attribution, staff session baseline | Positive and negative authentication tests; actor recorded on both registration records. |
| 3 | Schema migration and database roles; append-only audit/history controls | Data-preserving migration rehearsal, rollback rehearsal, runtime-role denial tests. |
| 4 | Shared RBAC and staff identity contract | Permission/scoping tests; confirmed MBSP authentication integration; separately validated OIDC provider capability and AIRS integration. |
| 5 | Legacy mapping and read-only lookup ingestion | Approved field mapping, scrubbed sample, repeatable import and reconciliation counts. |

Staging, a recorded restore drill, onboarding by a non-author and the ICT handover
pack remain Phase A prerequisites alongside this code work. Repository hosting,
staff authentication access, legacy data access and infrastructure ownership need
the relevant ICT/programme owners; none is assumed approved by this review.

The current roadmap specifies family-by-family operational cut-over with telco
first and permohonan last. Select the first operational slice within that agreed
sequence after the foundation and departmental discovery are ready.
