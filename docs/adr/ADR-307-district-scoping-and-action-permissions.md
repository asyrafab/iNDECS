# ADR-307: District scoping and per-action RBAC permissions

Date: 2026-10-27

## Status

Accepted.

## Context

ADR-305 introduced `core.roles`, `core.permissions` and `core.role_permissions`
with `perancang.pkm.access`, leaving district (`daerah`) scoping and per-action
route authorization as the next Phase A foundation increment (document 18).
Without district scoping and action-level permissions, any role with
`perancang.pkm.access` can view applications from every district and execute
both intake and completeness-review decisions.

## Decision

1. Add a nullable `district` column (`varchar(100)`) to `users`. A `null` value
   grants council-wide access across all MBSP districts; a non-null value
   (e.g. `Seberang Perai Utara`, `Seberang Perai Tengah`,
   `Seberang Perai Selatan`) restricts application queries and record access to
   applications with at least one site or lot in that district.
2. Seed action-level PKM permissions in `core.permissions` and
   `core.role_permissions`:
   - `perancang.pkm.access`: view PKM dashboard, list, application details and
     documents.
   - `perancang.pkm.register`: register new PKM applications, upload documents
     and resubmit amendments.
   - `perancang.pkm.review`: open completeness review, verify checklist items,
     confirm completeness or return for amendment.
3. Grant `intake_officer` only `perancang.pkm.access` and
   `perancang.pkm.register` (`JPBD` / `PKM`), enforcing separation of duties
   between intake registration and completeness-review decisions. Grant
   `planning_officer` and `senior_planning_officer` all three permissions for
   `JPBD` / `PKM`, and `system_admin` global grants.
4. Add route middleware `perancang.permission` (`EnsurePkmPermission`) and
   model scope `Application::scopeVisibleTo(User $user)` so list/dashboard
   queries and single-application actions enforce both action permission and
   district scope.

## Consequences

- District-assigned officers only see and act on applications within their
  district unless granted council-wide scope (`district = null` or
  `system_admin`).
- Intake officers can register applications and upload documents but cannot
  perform completeness-review decisions.
