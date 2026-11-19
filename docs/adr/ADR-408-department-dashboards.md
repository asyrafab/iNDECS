# ADR-408: Authorised departmental dashboards

Date: 2026-11-19

Status: Accepted for implementation at the owner's request.

Each department receives a read-only `/dashboard` under its existing route prefix.
The application chooser and workspace switcher open these dashboards; application
list and record URLs remain available. The existing `/dashboard` entry retains
its planning response and dispatches other staff to their authorised workspace.

Use one shared presentation for scoped application counts, complete status
breakdowns, recent applications and the officer's accessible tasks. Department
and type restrictions precede district scoping. Bangunan counts only permitted
families; LJUP keeps its existing unlocated-record visibility rule. Engineering
covers LJUP only until native PKT/PKP workflows become available. Missing module
or task grants confer no access. Supervisors may see team task totals using the
existing task access query; ordinary officers see their own tasks only.

All figures come from current database records. Manual task deadlines use the
Malaysian display date and are not statutory SLA measurements. No writes, grant
changes, new AI calls, payment totals or inferred readiness/approval are added.
Reuse the existing token-based cards, tables and native links in both themes.
