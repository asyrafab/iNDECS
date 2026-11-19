# Department dashboards

ADR-408 adds a dashboard to each existing departmental workspace:

| Department | Dashboard | Records covered |
|---|---|---|
| Perancang | `/perancang/dashboard` | PKM |
| Bangunan | `/bangunan/dashboard` | Permitted PB / PBK / PBS / PMT / TEL families |
| Kejuruteraan | `/kejuruteraan/dashboard` | LJUP; native PKT / PKP are not yet available |
| Landskap | `/landskap/dashboard` | PL |

The chooser, department switcher and staff landing routes open these pages.
ADR-410 shows all staff applications in both selectors; absent access is a grey
disabled non-link. Existing module guards remain authoritative. The native
workspace switcher animates using shared motion tokens and honours reduced motion.
**Daftar Mesyuarat** under **Ruang kerja** uses existing `osc.*` registration;
NADI is a distinct unavailable application until its own launch URL is configured.
ADR-411 removes the redundant Semua aplikasi shortcut and retains the last
authorised department menu while using Daftar Mesyuarat. The user-keyed session
preference stores only the department identifier, revalidates current access and
falls back safely for direct meeting links. Meeting-only users do not receive
department links. Daftar Mesyuarat alone is marked active on meeting pages.
Existing application list and record links remain valid. `/dashboard` retains
the planning response and routes other staff to an authorised departmental page.
Public accounts continue to use the public portal.

Counts, the full status breakdown and the six most recently registered records
share the same authorised department/type/district query. Engineering retains
the existing exception for LJUP records without structured sites/lots. Task
figures and up to five active tasks use `OfficerTaskAccess`: assigned officers
see their own work and supervisors see accessible team work. Target dates use
the configured Malaysian display calendar; they do not represent statutory SLA.

Deployment needs no new migration, seeder or permission. Existing module access
is required; task cards require the previously approved task grants as well.
In the local database, missing Landskap roles/module setup still needs separate
reviewed activation. Dashboard delivery does not create those grants.

Verify each route as a departmental officer and as a supervisor, including a
district-restricted officer, a Bangunan officer with only one family, and an LJUP
record without sites. Confirm other departments/public/inactive accounts cannot
open unauthorised dashboards. Dashboard GETs must not modify applications,
workflow, task records or audit history. Empty records and unavailable task
access have explicit messages. Responses use `Cache-Control: no-store`.

The shared view uses existing cards and tables, with a token-based
`.department-dashboard-grid` that stacks below 901px. Tables scroll inside cards.
Check both themes, desktop and mobile, and retain the normal sidebar behaviour.

Validation on 19 November 2026 used a clean temporary checkout and isolated
loopback PostgreSQL on port 5548, retaining the `indecs_test` safety guard:
915 PHP Unit/Feature tests passed, two optional fixtures skipped, 4,730 assertions.
All 40 JavaScript tests, full Pint and the production build passed. All four
test-data previews were inspected, including light/dark and desktop/mobile.
Concurrent legacy-import/GIS edits are excluded from this increment.
