# ADR-314: eOSC+ naming and the C10 schema

Date: 2026-10-29. Status: Accepted. Builds on ADR-213 (eOSC+ is core capability C10).

## Context

eOSC+ (the OSC meeting room) is built concurrently with the departmental modules
inside this codebase. It needs one set of names for tables, routes, permissions and
roles so that the modules do not collide and the identifiers stay predictable.

## Decision

- Product name: eOSC+ (Mesyuarat OSC). It is core capability C10, not a separate system.
- Tables live in the `core` schema with the prefix `osc_` (`core.osc_meetings`,
  `core.osc_meeting_members`, `core.osc_agenda_items`, `core.osc_agenda_item_papers`,
  `core.osc_agenda_versions`, `core.osc_department_settings`).
- Routes use the prefix `/osc` and route names `osc.*`.
- Permissions: `osc.meeting.view`, `osc.meeting.manage`, `osc.agenda.propose`,
  `osc.agenda.endorse`.
- Roles: `osc_secretary` (Setiausaha OSC) and `osc_steno` (STENO OSC).
- Every mutable record carries `row_version` for optimistic concurrency.
- PHP code lives in `App\Enums\Osc` and `App\Models\Osc`.

## Consequences

Later eOSC+ work follows these names without further decisions. Permission checks
go through `CorePermissionService` via `User::canPerformOsc()`. Renaming any of the
above needs a superseding ADR.
