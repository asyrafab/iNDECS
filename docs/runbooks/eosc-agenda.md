# eOSC+ meetings and agenda

Implemented increment: eOSC+ Plan 1 (foundation and agenda builder), 2026-10-29, on branch `feat/eosc-foundation` (not yet merged). Decision record: ADR-314. eOSC+ is core capability C10 inside iNDECS; screens are under `/osc`. This increment covers meetings, agenda items, papers and agenda publishing only. There is no map, decision panel, attendance or external attendee access yet (later plans).

## Deployment

After backing up the target database and reviewing the additive migration, run from the iNDECS root with the correct environment configuration:

```sh
php artisan migrate --path=database/migrations/2026_10_29_000000_create_osc_meetings_and_agenda.php --force
php artisan db:seed --class=OscSeeder --force
```

The migration creates `core.osc_meetings`, `core.osc_meeting_members`, `core.osc_agenda_items`, `core.osc_agenda_item_papers`, `core.osc_agenda_versions` and `core.osc_department_settings`. `OscSeeder` adds the roles `osc_secretary` and `osc_steno`, the permissions `osc.meeting.view`, `osc.meeting.manage`, `osc.agenda.propose`, `osc.agenda.endorse` and their grants. It creates no meetings or user accounts and can be rerun without duplicates. Do not rerun the full DatabaseSeeder on the consolidated development database just to enable this increment.

Rollback refuses to drop the tables once any meeting exists; use a reviewed data-preserving forward fix instead.

## Granting roles

A staff account has one role in `users.role`. Set it to `osc_secretary` or `osc_steno` for the people who run the meeting. Both receive `osc.meeting.view` and `osc.meeting.manage` and are called "managers" below. Only active staff accounts (`account_type = staff`) can use `/osc`; others get 403.

Existing roles already get grants from the seeder:

| Role | Department | Permissions |
|---|---|---|
| `planning_officer` | JPBD | view, propose |
| `senior_planning_officer` | JPBD | view, propose, endorse |
| `building_officer` | JBANG | view, propose |
| `senior_building_officer` | JBANG | view, propose, endorse |
| `system_admin` | all | view, manage, propose, endorse |

The `senior_*` roles act as heads of department for endorsement. Any other role needs a reviewed new grant in `core.role_permissions`; do not edit grants ad hoc.

## Creating a meeting

A manager opens `/osc` and chooses to create a meeting. Enter the meeting number (unique), date and time (must be in the future), venue, and two cut-offs, both on or before the meeting time:

- **Tutup cadangan** (proposal cut-off): after this, officers cannot propose items. Managers still can.
- **Tutup kertas kerja** (paper cut-off): accepted items without a paper after this time are flagged as missing a paper.

Choose the chairperson (set per meeting, not a fixed role), secretary, optional STENO and the members. All must be active staff. Members can be changed later; this is audited and refused once the meeting is closed or minutes confirmed. A new meeting starts as a draft.

## Proposing items

- **Linked item:** on a Bangunan or PKM application page the officer uses the button "Bentang ke mesyuarat OSC", picks a meeting and the purpose, and names the presenter. The same application can be tabled again at a later meeting (repeated tabling).
- **Standalone item:** from the meeting screen, for matters with no application. The officer's own department is used. A manager must choose the department.

Officers need `osc.agenda.propose` for the item's department. Proposals are refused after the proposal cut-off (managers excepted) and on meetings that are in progress, closed or minutes confirmed.

## Endorsement by the head of department (optional)

Endorsement is off by default. To switch it on for a department, insert or update a row in `core.osc_department_settings`:

```sql
INSERT INTO core.osc_department_settings (department_id, requires_endorsement, created_at, updated_at)
VALUES ('JPBD', true, now(), now())
ON CONFLICT (department_id) DO UPDATE SET requires_endorsement = true, updated_at = now();
```

Set `requires_endorsement` back to false to switch it off. There is no screen for this yet. When on, that department's new items wait for a head (a `senior_*` role holder with `osc.agenda.endorse` for that department) to press "Sokong" before the secretary can accept them. When off, items go straight to the secretary.

## Secretary: accept, return, order, publish

Managers review proposed items on the meeting screen:

- **Terima** accepts the item onto the agenda.
- **Return** sends it back to the department with a required reason, which the proposer sees.
- Accepted items are ordered with the reorder action.
- **Terbitkan agenda** publishes. Each publish creates a new version (1, 2, ...) that stores the ordered items and a list of changes against the previous version. Members are emailed a link only; the agenda and papers are never in the email.

Agenda changes are refused once the meeting is closed or minutes confirmed. Every change carries the row version, so a stale screen is rejected and must be reloaded.

## Papers

The item's presenter, proposer or a manager can upload a paper. Rules: PDF only, up to 50 MB, virus-scanned by ClamAV before storage (a detection, an unavailable scanner, an error or a timeout all refuse the upload; there is no bypass). Each upload is a new version and old versions are kept with a SHA-256 checksum. Every view of a paper is audited. Configure ClamAV as described in the document-verification runbook.

## What people see

| Person | Sees |
|---|---|
| Manager (secretary, STENO, system admin) | Everything for all meetings |
| Meeting member | Only the latest published agenda and the papers of items in it, once the meeting is no longer a draft |
| Presenter or proposer | Their own items, whatever the status, including a return reason |
| Head of department | All items of their own department, and the Sokong action |

## Staff accounts only

Until Plan 4 (attendee access) ships, members, chairperson and presenters must be staff accounts. External attendees (PSP, owner, outside agencies) cannot be added or log in. Plan meetings with staff only.

## Validation

`php artisan test` covers meetings, linked and standalone proposals, cut-offs, endorsement, accept/return, versioned publishing and changes, papers, visibility and closed-meeting refusals. Use the configured test database, never a production connection.
