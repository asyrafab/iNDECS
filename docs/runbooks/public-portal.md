# Public preparation portal

ADR-358 implements the first applicant workspace increment under ADR-352/354.

## Open locally

Apply `2026_11_04_000200_create_applicant_drafts.php` using the standard migration
workflow (`php artisan migrate`), then open `/portal` with an active public account
or a verified email-link guest. Existing login/Google/email entry points remain.
The application chooser's Portal Awam entry opens the dashboard. No public account
is seeded by this increment.

## Available

- Front page (ADR-361, supersedes the ADR-359 entrance): the Kaunter Maya split,
  with AMIRA on the left and **Fail saya** on the right. Drafts are listed as
  "Draf belum dihantar", and Permohonan baharu opens the type chooser. On phones,
  Fail saya fills the screen and a "Tanya AMIRA" bar opens AMIRA as a sheet.
- AMIRA avatar: the bubble mood follows the account. It shows `alert` when email
  is unverified, `happy` right after a draft save, and `calm` otherwise. To try
  the other moods in the browser console, run
  `AmiraBubble.setMood(document.querySelector('[data-amira-bubble]'), 'think')`
  and `AmiraBubble.setSpeaking(el, true)`.
- Draf saya: paginated private drafts, creation and editing with manual saves.
- Document uploads (ADR-385): compact name-and-status cards in a raised checklist
  window. Select a card for its description, attached file and upload actions;
  unsorted files open their sorting/removal controls the same way. Icons include
  accessible labels and a legend. Escape/backdrop/close returns to the list;
  polling preserves open dialogs and selected files.
- Buang draf (ADR-384): owner-only removal from Fail saya, the draft list and
  editor, with confirmation. Apply migration
  `2026_11_16_000700_add_soft_deletes_to_applicant_drafts.php` before deploying.
  Removed drafts disappear from the portal and quota; revisions and files remain
  for audit. Pending/paid checks and all paid review journeys prevent removal.
- Common fields: active PKM/Bangunan type, title, optional site address and notes.
- Akaun saya: existing email verification, guest claiming and Google linking.
- Simple public header and gear menu for account, drafts, helpdesk availability,
  themes and local AMIRA guidance (ADR-359). No public sidebar.

Each type's download icon lists that type's **public, active** department library
documents (ADR-362, `portal.library.download`). It falls back to "Belum tersedia"
when there are none. Internal or retired documents are never listed, and their
URLs return 404.

Permohonan baharu lists active available types and prefills the draft form without
creating a record. Owned drafts appear only as Draf belum dihantar, never as received
applications, because public formal intake is unavailable. Do not match staff applications by applicant email. Dialogs
support Escape, close/backdrop, focus trapping/return and inline no-JavaScript
fallbacks. Helpdesk ticket creation is labelled unavailable.

Only verified accounts can create or edit drafts. Staff and inactive accounts are
denied. Other applicants receive 404 for draft IDs they do not own. Each owner is
limited to 100 drafts, with 20 writes/minute per existing Laravel throttle.
Every successful save stores a revision snapshot transactionally. When a second
tab changes the same draft, the stale save fails and preserves input: copy any
needed edits, reload the current draft, reconcile, then save. There is no delete
or official-submit action.

## Service limits

Drafts have no official application reference or operational workflow. They never
write `applications`, accepted-submission proof, fees, payments or AIRS jobs.

Document upload, the checklist and the free local-OCR pre-check now run on drafts
(ADR-363; worker setup is in [precheck-worker.md](precheck-worker.md)). To see
check results locally, run `php artisan queue:work --queue=precheck` alongside the
server. ClamAV must be installed, or uploads are refused.

Applicant tracking, departmental forms, payment and official submission remain
subsequent increments. No paid AI
is called. Kejuruteraan and Landskap preparation types remain unavailable pending
their intake definitions. No migration of staff or legacy applications occurs.

## Verify

Run `php artisan test --filter=PublicPortalTest` against guarded PostgreSQL
`indecs_test`, followed by the full suite for shared-core regressions. Check the
dashboard, draft editor/list and account page on desktop and phones, including
dark mode, keyboard navigation, validation and concurrent tab saves.

## Draft details filled from dropped documents (ADR-390)

The pre-check job fills a dropped draft's provisional title, empty address and (LJUP) empty lighting type from
the cover letter. Filled fields show "Diisi daripada … — sila semak." until the applicant saves. If nothing is
filled, check that the letter was within the 20-file reading budget (`kaunter.precheck.read_files_per_draft`)
and that its subject follows "PERMOHONAN … BAGI CADANGAN … DI ATAS …". Run
`PLAN_REVIEW_GREEN_CAMOUR_PATH=<zip> php artisan test --filter=GreenCamourSortTest` for the real-package check.
