# ADR-389: Held confirmation and reversible modal dimming

Date: 2026-11-16

Status: Accepted (owner request)

## Decision

Replace native draft-removal confirmation with a shared styled portal dialog.
It names the selected draft, offers Batal, and requires a continuous 1.2-second
hold on Buang draf. Mouse, touch, pen and keyboard Space/Enter are supported.
Release, leaving the button, loss of focus, cancellation or page hiding resets
the hold. A progress fill and accessible instruction explain the gesture.
Only a completed hold submits the original CSRF/version-protected form once.
Removal triggers remain disabled until this confirmation behaviour is available.

Draft confirmation and document-description dialogs animate the dialog and its
backdrop on entry and exit. Escape, backdrop and cancel/close all use the same
exit path and restore focus after completion. Reduced motion closes immediately;
it does not remove the deliberate hold requirement. Existing deletion policy,
ownership checks and audit retention remain unchanged.
