# Operational Bangunan registration

Implemented increment: ADR-309, 2026-10-27. The application uses the existing Laravel core, authentication, permission registry, PostgreSQL records and Blade shell. No separate prototype or sample-data seeder is required.

## Local use

Open `/bangunan` and sign in with an existing authorised staff account. `building_officer` and `senior_building_officer` grants are scoped to JBANG; system administrators have explicit global grants. This increment does not create accounts or change anyone's role. A district-scoped officer sees and registers only their district; type-specific grants restrict the family list, totals and actions.

Register PB, PBK, PBS, PMT or TEL. PB requires an OSC reference. Provide the applicant, site, at least one lot and the basic department fields. The application writes common records to the existing core tables and department fields to `bangunan.app_pb_applications`, with actor-attributed audit and stage history in the same transaction. Submitted party snapshots remain attached to the case even if the shared party record changes later.

Open the application to review details and, with review permission, start document verification. This uses the pinned core workflow template and requires the displayed row version. A stale or repeated action is rejected without another history entry. Lists and details use the existing design tokens and shared theme controls.

## Deployment

After backing up the target database and reviewing the additive migration, run from the iNDECS root with the correct environment configuration:

```sh
php artisan migrate --path=database/migrations/2026_10_27_000000_create_bangunan_registration_and_workflow.php --force
php artisan db:seed --class=BangunanWorkflowSeeder --force
```

The dedicated seeder adds permissions, grants and the first version of the five workflow templates. It creates no operational applications or user accounts and can be rerun without duplicates. Existing core departments, family types and roles must already be provisioned by the platform baseline. Do not rerun the full DatabaseSeeder on the consolidated development database just to enable this increment.

For local development, use `php artisan serve --host=127.0.0.1 --port=8000`. Linux staging/production must use the platform deployment arrangement, not the development server. PHP application code has no Windows-specific paths. Ubuntu CI explicitly uses PostgreSQL port 5432; the current workstation test database uses 5434.

## Validation

`php scripts/verify-database.php` rebuilds only the guarded disposable `indecs_test` database and checks migration/rollback/re-migration. `php artisan test` covers all families, permissions, department/type/district denial, references by family/year, atomic registration, optimistic workflow versions and append-only audit/history. Use the configured test port, never a production connection.

The registration tests optionally save rendered Blade pages under ignored `.tools/bangunan-ui/` when `INDECS_CAPTURE_VIEWS=1`. This is private layout verification using test records; it is not a module mode or an authentication bypass.

## Boundaries and next increment

The registration increment implements registration, list/search/filter, case details and entry into document verification. ADR-310 now adds document-verification completion. Neither increment collects fees, issues approvals or permits, calls AIRS, or replaces the legacy system. Legacy lookup stays separate at `/bangunan/legacy`. No live legacy data has been converted into new applications.

Document verification is now implemented under ADR-310; see [the document-verification runbook](bangunan-document-verification.md) for catalogue configuration, scanned revisions, corrections and guarded completion. Next: fee assessment using department-confirmed rules and subsequent guarded transitions. Keep operational fields in the department extension and reusable behavior in core. PKM workflow migration remains module rollout Phase 2 (ADR-211).

Migration rollback refuses to drop the new structures while operational applications reference a workflow template. Once registrations exist, use a reviewed data-preserving forward fix or restore/reconciliation plan. Never delete history to force a rollback.
