# Shared officer work queue

ADR-407. The staff queue is `/tugasan`, linked as **Tugasan pegawai** in the
existing workspace. It supports existing PKM, Bangunan family, LJUP and PL
application records. It does not activate an application type or create an
application on behalf of a department.

## First-release authority

The owner selected: **supervisors assign/reassign; the assigned officer completes**.

| Department | Officers with own-work access | Assignment supervisors |
|---|---|---|
| Perancang | planning_officer, senior_planning_officer | senior_planning_officer |
| Bangunan | building_officer, senior_building_officer | senior_building_officer |
| Kejuruteraan (LJUP) | engineering_officer, senior_engineering_officer | senior_engineering_officer |
| Landskap | landscape_officer, senior_landscape_officer | senior_landscape_officer |

These grants intersect existing module access, department/type and district
rules. System admin receives task permissions but still passes the record
adapter. Only an active officer of the task's department with access to the
specific record can be selected. A supervisor cannot complete another officer's
work. A Penolong Pengarah's existing library powers do not imply task assignment
authority and this increment does not add that grant.

Permissions are `core.tasks.view`, `core.tasks.work` and `core.tasks.assign`.
Possession of a task never grants application or document access. The team view
only includes the teams/records the signed-in supervisor may manage; ordinary
officers still see only their own work even if they request `scope=team`.

## Use

1. A supervisor chooses **Cipta tugasan**, searches/picks an authorised
   application, selects an eligible officer, and records a title, instructions
   and optional manual target date.
2. The officer sees the work under **Tugasan saya**, opens it and chooses
   **Mulakan tugasan**. The application link opens the existing department record.
3. The assigned officer records a completion note and may select up to 20 scanned
   document revisions as evidence. Completion pins their IDs, revision numbers,
   names and SHA-256 checksums in immutable history. Historical files are labelled
   by revision. Missing, tampered, unscanned or foreign-record files are refused.
4. The supervisor can reassign unfinished work or record cancellation with a
   reason. Reassignment returns work to the open state and preserves the earlier
   assignee/action snapshots. Completed/cancelled tasks are closed permanently in
   this release; they cannot be rewritten or reopened through the service.

Queue filters cover own/authorised team work, department, status, overdue manual
targets and task/reference search. Results are paginated. An empty list grants no
broader access. A task completion does not approve an application, change its
stage/status/assignee, rewrite AIRS findings, or issue a letter. Manual dates are
not SLA/statutory deadlines. No notification, historical backfill, automatic task
creation, cross-department delegation or supervisor acceptance step is enabled.

## Conflicts and audit

Actions check an expected task revision under a PostgreSQL row lock. A stale
writer receives HTTP 409 and must reload/reconcile; it does not append another
history action. Task changes, the immutable action/evidence rows and the audit
event commit together. Any failure rolls back the entire mutation. Evidence
downloads recheck current task/record access and the pinned file checksum, and
record an audit event. Referenced documents cannot be deleted through SQL while
an immutable task evidence reference exists.

Staff who lose module/task/district access lose queue, detail and evidence access.
Reassignment removes the previous assignee's own-work access unless they separately
hold supervisor authority for that record.

## Deployment

Use the reviewed application deployment process and retain a verified backup.
Apply only this increment's additive migration, then seed its scoped permissions:

```powershell
php artisan migrate --force --path=database/migrations/2026_11_18_000100_create_officer_work_tasks.php
php artisan db:seed --class=OfficerTaskSeeder --force
```

The standard `DatabaseSeeder` also calls `OfficerTaskSeeder` after the existing
module/library seeders for fresh installations and isolated tests. Do not run the
full demo seeder on an operational database merely to enable tasks. Restart the
relevant application processes when deploying code. No existing application
assignment, workflow history or document revision is backfilled or rewritten.

Rollback refuses to discard existing task records. Use a reviewed forward repair
or restore/reconciliation when task history exists; never disable immutable
triggers to make rollback succeed. Development verification uses guarded
PostgreSQL `indecs_test`; normal application data/permissions are not changed by
running the tests.

## Verify

```powershell
php artisan test --filter='OfficerWorkQueueTest|OfficerTaskConcurrencyTest'
php scripts/verify-database.php
php artisan test
php vendor/bin/pint --test
node --test tests/Js/*.test.cjs tests/Js/*.test.mjs
npm run build
```

The concurrency test starts two independent PHP database clients, proves both
wait on the same row lock, and verifies one action succeeds while the other
returns a stale conflict. Browser acceptance should cover supervisor creation,
own/team filtering, officer start/completion, escaped notes/history and denied
cross-department access. Keep the existing GitHub billing and unpatched
development-dependency advisory limitations visible; do not claim hosted CI green.
