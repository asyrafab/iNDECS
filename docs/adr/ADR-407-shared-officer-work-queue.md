# ADR-407: Shared application-linked officer work queue

Date: 2026-11-18.

Status: Accepted for the owner-requested shared work-queue implementation.

## Decision

Build one application-linked staff task service in `core`, with a queue at
`/tugasan`. Existing department adapters determine subject visibility. A task
never grants access to its application, documents or another department. Preserve
LJUP's established unlocated-record rule and existing PKM assignment semantics.

Each task has an assignee, manual due date, revision and an explicit lifecycle:
open, in progress, completed or cancelled. Supervisors create, assign/reassign
and cancel; only the current assignee starts/completes work. Reassignment returns
unfinished work to open. Completed/cancelled tasks cannot be rewritten or reopened
in this increment. A note is required for reassignment, cancellation and completion.

Use explicit scoped `core.tasks.view`, `core.tasks.work` and `core.tasks.assign`
permissions. Seed view/work for existing departmental officer access grants, and
assignment for the department's existing senior officer roles plus system admin.
Do not infer assignment power from `penolong_pengarah` or library authority.
Supervisor here means the holder of the explicit assignment permission, not a
new statutory approver. No normal database permissions change during development.

Create task changes, immutable task action snapshots and the existing audit row
atomically under a row lock and expected revision. Append-only action rows pin
actor, before/after values, note and optional scanned application-document
revision IDs/checksums. Reject foreign or unscanned evidence. Evidence downloads
recheck task and subject access and file checksum; task history never stores a
public file URL. Preserve task/history references with restrictive foreign keys.

Task actions do not mutate application stage/status, application assignment,
official decisions, AIRS findings or SLA clocks. No automatic historical-task
backfill, cross-department delegation, reminders, official issuance, supervisor
completion approval or reopening is implemented. Due dates are explicitly manual,
not statutory SLA deadlines. The first release supports PKM, Bangunan families,
LJUP and PL where the underlying record already exists and is accessible.

## Verification and deployment

PostgreSQL tests cover department/district/record isolation, explicit permissions,
assignee-only completion, stale revisions, audit rollback, immutable history,
evidence revision pins and unchanged application workflow. Queue filters and
forms use existing workspace classes and Bahasa Melayu. Migrations are additive;
rollback refuses to discard task history. Deployment requires the migration and
scoped seeder together; no source application needs to be re-registered.

GitHub billing and the existing unpatched development dependency advisory remain
release limitations; local verification must not be described as green hosted CI.
