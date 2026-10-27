# ADR-308: iNDECS Bangunan read-only legacy lookup schema and web screens

Date: 2026-10-27

## Status

Accepted.

## Context

Phase A of the iNDECS Bangunan roadmap (`docs/bangunan/13-indecs-bangunan-module-spec.md`
§5.1 and `docs/dokumentasi/10-pemetaan-dan-migrasi.md` §8.1) requires a read-only
sync copy of the legacy `MPSP_Bangunan` tables in schema `bangunan` (`bangunan.legacy_*`)
with list and detail screens inside the iNDECS Bangunan shell (`/bangunan/legacy`).
This delivers the Phase A exit criterion — staff can search and inspect historical
Bangunan applications in iNDECS while the legacy WinForms client remains the sole
writer until each application family cuts over in Phase B (ADR-202: no dual-writing).

Database verification against `MPSP_Bangunan` (`PB_EVIDENCE_VERIFICATION_REPORT.md`)
established three critical structural facts:

1. `PERMOHONAN1.PmnID` (`int` identity) is the only physical primary key;
   `PmnNoSiri` (`nvarchar(50)`) is 100% unique in practice (`32,892` rows) and
   links `PERUNDING` (`PrdID_PmnNoSiri`) and `BANGUNAN1` (`BgnID_PmnNoSiri`).
2. `PmnNorujukan` (`nvarchar(50)`) is a non-unique file reference shared across
   original submissions, amendments and phases (`26,939` distinct values across
   `32,892` rows) and links `CCC` (`CCC_PmnNoRujukan`).
3. Legacy status display in `frmMain.fetch` evaluates branch dates (`PmnTkhTolak`,
   `PmnTkhBatal`, `PmnTkhTarikBalikR`, `PmnTkhLulus`, `PmnTkhTangguh`) ahead of
   raw `PmnStatus` codes (`B`, `L`, `S`, `BK`, `K`, `T`, `CCC`).

## Decision

1. Provision PostgreSQL schema `bangunan` and four read-only legacy mirror tables:
   - `bangunan.legacy_permohonan` (keyed by `pmn_id`, unique index on `pmn_no_siri`,
     btree and `pg_trgm` GIN indexes on `pmn_no_rujukan`, `pmn_no_siri`, `pmn_tajuk`,
     `pmn_pemohon`, `pmn_no_lot` and `pmn_tempat`).
   - `bangunan.legacy_perunding` (keyed by `prd_id`, indexed on `prd_pmn_no_siri`).
   - `bangunan.legacy_bangunan` (keyed by `bgn_id`, indexed on `bgn_pmn_no_siri`
     and `bgn_pmn_no_rujukan`).
   - `bangunan.legacy_ccc` (keyed by `ccc_id`, indexed on `ccc_pmn_no_rujukan`).
2. Enforce read-only protection on the Eloquent models (`LegacyPermohonan`,
   `LegacyPerunding`, `LegacyBangunan`, `LegacyCcc`) so web controllers and
   application services cannot mutate or delete legacy mirror rows; only the
   sync/import command writes to `bangunan.legacy_*` via the query builder.
3. Evaluate effective legacy status (`effective_status_code` and Bahasa Melayu
   label) using the verified `frmMain.fetch` branch-date precedence before
   falling back to `PmnStatus`.
4. Seed five `JBANG` application family codes (`PB`, `PBK`, `PBS`, `PMT`, `TEL`)
   per ADR-210, `JBANG` roles (`building_officer`, `senior_building_officer`),
   and permission `bangunan.legacy.view` in `core.permissions` and
   `core.role_permissions` (granted to `JBANG` roles and globally to
   `system_admin`), respecting `users.district` scoping.
5. Expose read-only lookup routes under `/bangunan/legacy` (`bangunan.legacy.index`
   and `bangunan.legacy.show`) guarded by `bangunan.legacy` middleware, and
   provide an idempotent console command `bangunan:sync-legacy` that loads
   scrubbed legacy snapshots and prints reconciliation counts.

## Consequences

- Authorized `JBANG` officers and `system_admin` can sign in to iNDECS and search
  legacy Bangunan records by file reference (`PmnNorujukan`), serial (`PmnNoSiri`),
  lot, applicant, location, district and status without touching the legacy SQL Server.
- Shared file references (`PmnNorujukan`) explicitly display sibling applications
  on the same file and surface multi-parent CCC warnings (`needs_review`) rather
  than silently guessing a single parent.
- All changes are reversible in `down()` so `scripts/verify-database.php` continues
  to verify `migrate:fresh`, `migrate:rollback` and `migrate` on `indecs_test`.
