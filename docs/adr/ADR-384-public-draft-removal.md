# ADR-384: Public applicants may remove preparation drafts

Date: 2026-11-16

Status: Accepted (owner request)

## Decision

The public applicant may remove their own preparation draft from Fail saya, the
draft list or the draft editor. The action uses a CSRF-protected DELETE request
with confirmation and an optimistic version check. Foreign or removed draft IDs
return 404. Removal is unavailable once a pending/paid check payment or a paid
check journey exists, including amendment and handover states. Editing permission
alone does not imply removal permission.

Removal soft-deletes the draft and writes an attributed audit event in the same
transaction. Revisions, uploaded documents and other history remain intact;
removed drafts are excluded from normal queries, portal counts and quota usage.
The draft row lock serializes removal with payment initiation and confirmation.
No official application or workflow state is deleted or changed.

## Consequences

A schema migration adds deleted_at to core.applicant_drafts. There is no public
restore action. Existing tokens, buttons and form confirmation behaviour are reused.
