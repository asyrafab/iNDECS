# Department database standardisation (ADR-393)

## Storage contract

All four departments share `core.applications` and its party, site, lot, document,
requirement, workflow, payment, recommendation, history and audit tables. Completeness
cycles use `core.completeness_reviews` and `core.completeness_items`; versioned
catalogue checklist snapshots remain a separate shared concept. This upgrade preserves
existing process rules rather than turning a PKM review into a Bangunan review.

| Department | Application extension | Draft particulars |
|---|---|---|
| Perancang | `perancang.app_pkm_applications` | `perancang.app_pkm_draft_particulars` |
| Bangunan | `bangunan.app_pb_applications` (PB/PBK/PBS/PMT/TEL) | `bangunan.app_pb_draft_particulars` |
| Kejuruteraan | `kejuruteraan.app_pkt_applications`, `app_pkp_applications`, `app_ljup_applications` | Matching `*_draft_particulars` tables |
| Landskap | `landskap.app_pl_applications` | `landskap.app_pl_draft_particulars` |

Each application extension has an `application_id` ULID primary/foreign key,
timestamps and restrictive deletion. Bangunan's former numeric primary key remains
unique, with its original sequence. Engineering/landscape extensions are created by
an application-insert trigger; PKM/Bangunan registration supplies their required
department fields in its existing transaction. The database rejects cross-type
extensions and changing the type of an operational application.

Applicant-declared project particulars previously accepted for every portal type are
preserved in the respective draft extensions. Their names and model accessors stay
compatible. Lighting declarations exist only in the LJUP draft/application extensions;
the separate `kejuruteraan.app_ljup_check_particulars` preserves each journey's own
lighting snapshot. Do not derive a paid journey's snapshot from its mutable draft.
Old extension rows remain when an unpaid draft changes type; only the selected type's
particulars are read. Historical draft revisions remain unchanged.

New engineering/landscape assessment columns are nullable storage, not an approved
intake form, calculation, unit contract or compulsory checklist. Confirm units and
validation when the relevant feature is implemented. PKT, PKP and PL are inserted
inactive only if absent; owner-maintained existing type configuration is preserved.
Public portal allowlists, permissions, fees and routes are unchanged.

## Verification

Run on the isolated `indecs_test` PostgreSQL database:

```powershell
php artisan test --compact --filter=DepartmentDatabaseStructureTest
php scripts/verify-database.php
php artisan test --compact
php vendor/bin/pint --test
```

The populated upgrade test starts at the old schema and verifies exact records for
all four departments, PKM components/cycles, Bangunan numeric IDs, documents, histories,
payments, accepted manifests, revisions and soft-deleted draft particulars. It checks
that draft lighting and journey lighting stay independent and audit mutation still fails.

## Deployment

1. Use a reviewed deployment with green checks. Back up the complete database with
   `pg_dump --format=custom`; record its SHA-256 and verify it with `pg_restore --list`.
   Keep the backup private and outside source control. Test restoration in a separate
   database for production deployment.
2. Pause web writes and drain/stop queue workers and scheduled writers. Use maintenance
   mode and deploy the matching application version; this is a coordinated schema/code
   cutover. Do not mix old and new worker binaries.
3. Run **only** these two migrations in order, preferably in one outer PostgreSQL
   transaction through Laravel's migrator. Do not apply unrelated pending migrations.

   ```powershell
   php artisan migrate --force --path=database/migrations/2026_11_17_000300_standardise_department_database_structure.php --path=database/migrations/2026_11_17_000400_move_department_particulars_to_extensions.php
   ```

4. Compare row counts and checksums of the relocated tables to the backup/baseline.
   Reconstruct each old draft from its core row and selected extension and compare all
   six former fields, including null values, timestamps and soft deletion. Compare the
   journey's former lighting field with its own extension, not its draft. Verify all
   AIRS/accepted/audit/history records remain unchanged. Check the expected engineering
   and landscape extension counts by application type.
5. Clear cached configuration if applicable and restart all application/queue processes
   so the expanded search path and schema-qualified models take effect. Smoke-test
   authorised Perancang/Bangunan/LJUP access and the owner's draft forms, then reopen.

The migrations take exclusive source-table locks and abort on unsupported draft types,
unexpected non-LJUP lighting data or mismatched application extensions. PostgreSQL
schema moves retain object identities, dependent foreign keys, sequences, indexes and
triggers. Imported/historical schemas and public identity/infrastructure are untouched.

## Recovery

Empty-database rollback is supported for migration verification. Once operational
applications or drafts exist, rollback deliberately refuses to remove department
extensions: the former schema has no destination for new engineering/landscape data.
Keep maintenance mode active, use a reviewed forward fix, or restore the verified
backup with reconciliation of any post-cutover writes. Never use `migrate:fresh`,
truncate, drop-cascade or disable audit triggers on the operational database.

## Local execution — 2026-11-17

Applied only the two ADR-393 migrations to the local `iNDECS` database, in one
outer transaction after pausing web writes and checking that the queue was empty.
A complete custom-format backup was created and its archive directory verified.
Before/after row counts and content checksums matched for 243 existing tables;
draft/journey rows were reconstructed with their extension particulars for comparison.
Historical JSON containing escaped control characters was hashed using its original
JSON representation, without converting or rewriting the stored data.

The local model smoke check read all 24 drafts (including soft-deleted rows), the
existing application and its paid journey. The site geometry remains SRID 4326.
Maintenance mode was removed, the supervisor restarted all four queue workers, and
the HTTP health and guest-home endpoints returned 200.

Validation: full PHP suite 873 passed, 2 skipped; populated-upgrade/storage tests
25 passed; migration fresh/seed/rollback/re-migrate and Pint passed. These local
checks used PostgreSQL 18.6, the installed server; PostgreSQL 16 CI remains required
before merge. Backup files and the per-table `cutover-receipt.json` are private local
artifacts under ignored `output/database-standardisation/`, not source-controlled data.
