# ADR-358: Public portal and private preparation drafts

Date: 2026-11-04. Status: Accepted for the first portal increment.

## Decision

Extend the existing `/portal` public identity entry point with an authenticated
applicant workspace: dashboard, paginated draft list, create/resume draft and the
existing account controls on a separate account page. Reuse the navy workspace,
Lucide icons, Bahasa Melayu and shared appearance tokens. Public identity entry
pages retain their existing unframed layout.

Verified active public and email-link guest accounts may save preparation drafts.
Every query is scoped to the authenticated owner; staff and other applicants have
no access. Drafts live in `core.applicant_drafts`, separate from official
applications, with append-only snapshots in `core.applicant_draft_revisions`.
Each successful save increments a version inside the same transaction; stale
edits are rejected with the user's input preserved. No deletion UI is introduced.

The initial fields are application type, proposal title, site address and notes.
Offer active PKM and Bangunan types from the existing registry, with active
departments. These are common preparation fields, not departmental extensions or
confirmed technical particulars. Kejuruteraan and Landskap remain planned until
their intake definitions exist. Draft IDs are private identifiers, not official
application references. Limit each owner to 100 drafts and throttle writes.

## Boundaries

Saving a draft does not create an official application, stage/status history,
invoice, payment clearance, accepted submission or AIRS job. No upload, local OCR,
precheck, paid AI, submission action or public access to staff records is enabled.
The UI states these service limits explicitly. Subsequent increments implement
the remaining gates in ADR-352/354 and the shared integration contract.

## Validation

PostgreSQL feature tests cover owner isolation, public/staff/verification gates,
validation, immutable revision snapshots, stale updates and absence of official
submission side effects. Apply migrations before opening the portal locally.
