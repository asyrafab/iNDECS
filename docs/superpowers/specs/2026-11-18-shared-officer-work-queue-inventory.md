# Shared officer work queue: first-step inventory

Date: 2026-11-18. Baseline: merged main `c92f664`.

Status: Discovery/proposal for FIRST-01 of the departmental delivery plan.
The owner subsequently selected the shared queue and confirmed supervisor
assignment with assignee-only completion; implementation is recorded in ADR-407.
This inventory does not approve task permissions, change workflow, or enable new
departmental intake. No application code or operational data was changed.

## Existing assignment and history

| Existing source | Verified behaviour | Consequence for shared tasks |
|---|---|---|
| `app/Models/Application.php` | Shared application has `assigned_to`, `assigned_at`, `due_date`, `priority` and `row_version`; assignee relation points to User. | Reuse the application ULID and existing assignment context; an application assignee is not a complete task lifecycle. |
| `app/Services/PkmWorkflowService.php` | Starting completeness review and resubmitting set the actor as application assignee, reset assignment time and set five weekdays from now as due date. Both update application state/version inside a transaction and append history/audit. | Preserve this PKM behaviour. Do not reinterpret its five-weekday date as a confirmed cross-department statutory SLA, or create historical tasks automatically. |
| `app/Services/WorkflowService.php` | Configured transitions lock the application, check access and expected version, resolve a permitted transition and run checklist guards before writing stage/status and history/audit. | A task action needs its own concurrency and action history; completing work must not call a workflow transition implicitly. |
| `app/Models/StageHistory.php`, `app/Models/AuditLog.php` | Shared append-only model guards protect historical writes; prior database work also enforces append-only history. | Add task-specific assignment/action history alongside the shared audit trail. Do not fabricate stage changes for task-only actions. |
| `app/Http/Controllers/DashboardController.php` | Existing dashboard begins with a PKM-only query. | It cannot serve as a four-department queue by merely removing the PKM filter. |

## Access boundaries to retain

| Department | Existing access path | Required queue adapter behaviour |
|---|---|---|
| Perancang | PKM type filter, staff module permission, `visibleTo` district scope; record checks use `canAccessApplication`. | Apply both type/permission and district/record constraints before returning rows or links. |
| Bangunan | Permission-filtered supported types, `visibleTo`, then `canAccessApplication` on records. | Preserve per-type permissions and existing record/document access; no widening through a task link. |
| Landskap | Permitted landscape types and module access, `visibleTo`, then `canAccessApplication` on records. | Preserve JLAND/type constraints and inactive-type configuration. |
| Kejuruteraan LJUP | `canPerformLjup` and `visibleToLjupOfficer`; its district scope deliberately allows LJUP records without structured site/lot rows. | Use the existing LJUP path, including its documented unlocated-record behaviour. Do not substitute the generic access method. |

`Application::visibleTo` provides district filtering, not a complete module
authorization policy. `User::canAccessApplication` currently handles PKM and PL
explicitly and otherwise follows the Bangunan path; it is not an LJUP access
adapter. A generic queue must compose the established department-specific rules,
not treat either helper alone as universal authorization.

`CorePermissionService::userCan` checks active staff, active role and scoped
department/type grants. Existing view/review/workflow permissions do not imply
new create/delegate/complete-task authority. Being assigned a task must not grant
access to an otherwise forbidden record or document.

## Proposed first bounded implementation

1. Application-linked staff work only. Independent departmental operations and
   cross-department delegation remain subsequent configured increments.
2. Shared task records with an application subject, department context, assignee,
   manual due date, status and action revision. Names and exact schema belong in
   the implementation ADR; no migration is supplied by this inventory.
3. Explicit create, assign/reassign, start and complete actions, each checked in
   the service against task authority and the subject adapter. Persist a task
   update, attributed action history and audit atomically under row lock and
   expected revision. An audit/history failure rolls back the entire action.
4. Completion records a note and authorized evidence references. Pin document
   revisions rather than mutable current-file pointers; replacing a document
   must not rewrite past completion evidence.
5. Own-work queue first, then authorized supervisor filters. Never return counts,
   titles or document links from inaccessible subjects. Manual deadlines remain
   distinguishable from future policy-driven SLA dates.
6. Preserve current application assignment and workflow fields. No historical
   backfill, auto-approval, official letter issuance or notification side effect
   is implicit in completing a task.

## Decisions still needed for the implementation ADR

- Which existing roles may create, assign/reassign and supervise work in each
  department/unit; whether officers may self-assign or complete another person's
  task. No extra grants are inferred from role names.
- Whether completion requires supervisor acceptance, and how correction,
  cancellation, reopening and inactive assignees should work in the first release.
- Required completion evidence and the historical-document access contract.
- Which confirmed application types participate first. The delivery plan proposes
  PKM and PL proofs followed by Bangunan/LJUP adapters; inactive intake remains
  inactive, and existing cases must not be fabricated to demonstrate coverage.

## Acceptance cases for the next increment

- Own/team views respect department, type, district and document permissions,
  including the existing LJUP unlocated-record rule. Public/inactive accounts and
  unauthorized staff receive no task/record metadata.
- Two writers at one task revision produce one successful action and one stale
  conflict, with no duplicate history or partial audit.
- Assignment and completion preserve application stage/status, PKM completeness
  cycles, accepted-submission proof and AIRS review findings.
- Reassignment retains earlier actors/dates; completion cites the same document
  revision after a replacement upload; inaccessible evidence is refused.
- Shared PostgreSQL regression covers all department adapters. Hosted CI remains
  dependent on resolving the GitHub billing lock and the unpatched picomatch
  advisory already recorded in `progress.md`.

This inventory is preparatory. The listed policy decisions remain open; no claim
is made that FIRST-01 departmental validation or the officer queue is complete.
