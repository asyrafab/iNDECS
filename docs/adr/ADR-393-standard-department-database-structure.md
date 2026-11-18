# ADR-393: Standard department database structure

Date: 2026-11-17.

Status: Accepted by the owner for implementation on 2026-11-17.

## Decision

All four departments use one set of operational tables in `core`: applications,
development cases, parties, sites, lots, documents, requirements, reference sequences,
assignment, history, audit, workflows, payments and recommendations. Move the earlier
unqualified operational tables from `public` into `core` using PostgreSQL `SET SCHEMA`;
preserve rows, keys, sequences, indexes, foreign keys and append-only triggers. Identity
and Laravel infrastructure stay in `public`; imported and historical schemas are untouched.

Department extensions use `application_id` (the core ULID) as primary and foreign key,
timestamps and restrictive deletion. Perancang keeps `app_pkm_` in `perancang`;
Bangunan keeps `app_pb_` in `bangunan`; Kejuruteraan uses `app_pkt_`, `app_pkp_` and
`app_ljup_` in `kejuruteraan`; Landskap uses `app_pl_` in `landskap`. LJUP's prefix is
an explicit extension of the naming table. Preserve Bangunan's existing numeric `id`
as a unique compatibility identifier, including its sequence; the application ULID
becomes its primary key. Department fields never move onto `core.applications`.

Create typed, nullable engineering and landscape extension fields from the existing
design direction, without inventing mandatory intake rules. Backfill extensions for
existing applications; future engineering/landscape applications create their extension
in the same transaction. Enforce application type at the database boundary. Register
PKT, PKP and PL as inactive if absent: this does not enable public intake or new routes.

Move PKM's completeness-cycle tables to generic `core.completeness_reviews` and
`core.completeness_items`, retaining all identifiers and cycle records. Expose generic
models, with the existing PKM model classes retained as compatibility adapters (including
audit type identifiers). Completeness cycles and versioned document-catalogue snapshots
remain distinct core concepts, available to every department. This schema change does
not replace reviewed process rules or rewrite in-flight workflow state; PKM's existing
service remains its process adapter, and Bangunan retains configured workflow guards.

Portal particulars move from the shared draft row into typed department/type extension
tables keyed by `draft_id`, following the same extension pattern. Preserve the existing
five project particulars in every type's extension because the portal previously allowed
them for every type; these are applicant declarations, not authoritative departmental
assessments. Only LJUP's extension contains `lighting_types`. Move the journey's declared
lighting snapshot into a Kejuruteraan extension keyed by `journey_id`; preserve its
independence from the mutable draft and formal application. Keep the model attribute API
compatible for forms, local prechecks, AMIRA and append-only revision snapshots.

## Preservation and deployment

Use new transactional migrations, never edit old migrations or truncate operational
tables. Lock source tables during relocation/backfill and abort on unsupported types
or unexpected data rather than discard it. Do not rewrite audit rows, revision snapshots,
accepted-submission manifests, AIRS evidence, stage/status or payment state. Test the
upgrade with populated pre-migration fixtures and test all departments on PostgreSQL.

Deploy schema and application code together during a write pause; restart web/queue
processes. Take a database backup first. Rollback may reconstruct the previous structure
only when no operational applications/drafts exist; otherwise use a reviewed forward fix
or restore/reconciliation, because engineering/landscape fields have no old destination.
Do not run unrelated pending migrations as part of this deployment.
