# ADR-305: Core RBAC permission registry

Date: 2026-09-21

## Status

Accepted.

## Context

Phase A requires RBAC and department/application-type scoping before iNDECS
Bangunan workflows are added. The current PKM slice authorises access from
hard-coded `users.role` and `users.department_id` values. That is acceptable for
the prototype, but it cannot express Bangunan roles, shared modules, or future
district/application-type scope.

Installing a full permission package remains possible, but this increment needs
a small, auditable core contract that keeps existing users working and avoids
changing the current workflow surface while the repository already contains
large Phase A foundation changes.

## Decision

Add core RBAC registry tables:

- `core.roles`
- `core.permissions`
- `core.role_permissions`

`users.role` remains the assigned role code for now. Access checks read
role-permission grants from `core.role_permissions`, scoped by department and
application type where required. A grant with a null department or application
type applies globally for that dimension.

PKM access now asks for `perancang.pkm.access` for department `JPBD` and
application type `PKM`. Seed data grants that permission to the existing
Perancang roles and to `system_admin`.

Public and guest accounts are excluded before the permission lookup, so public
registration, Google login and email-link flows cannot acquire staff access by
choosing a role value.

## Consequences

- The hard-coded PKM role list is replaced by seeded RBAC data while preserving
  current login and route behaviour.
- Bangunan can add permissions such as `bangunan.application.approve` without
  changing user-table columns.
- District scoping, separation-of-duty checks, per-action route middleware and a
  package-backed permission API remain later Phase A work.
