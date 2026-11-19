# ADR-412: Shared departmental workspace menu

Date: 2026-11-19

Status: Accepted at the owner's request.

Extend ADR-411's remembered, revalidated department context to officer tasks and
the department library. All workspace pages use that context for identity,
application selection and navigation; shared pages highlight only their own link.

Use one menu definition and presentation for all four departments. Ruang kerja
contains dashboard, applications, registration, officer tasks and meeting
registration. Bantuan contains the department library and AMIRA guidance. Existing
authorised Bangunan legacy lookup remains an additional department-specific link.
Unavailable actions are grey non-links, never invented routes or new grants.
Engineering officer registration remains unavailable until its workflow exists.
Meeting-only/library-only users receive generic identity and disabled department
actions if they have no authorised departmental workspace.

This is navigation consistency, not a new departmental intake or review workflow.
Task/library record queries, permission checks, assignments and audit remain
unchanged. Remember only the department identifier under the existing user-keyed
session preference; do not silently filter shared records by the menu context.
