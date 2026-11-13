# ADR-340: Full resting image stack and tabbed login dialog

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested three opaque image boxes at rest, upper-right on top, no image
captions, more images and a tabbed credential popup opening from Log Masuk with a
small overshoot, greying and frosting the background.

## Decision

When conveyor movement stops, settle the three current photos into lower-left,
middle and upper-right positions with full opacity and ordered layers. Preserve
hover-driven travel and four speed tiers. Remove visual captions but retain alt
text and accessible names. Expand the locally served, attributed image pool.

Use a native modal dialog opened by the login link. Animate it from the trigger's
position and scale, with one slight overshoot, then settle. Use token-defined
greyscale/blur backdrop and scrim as a narrow owner-approved frosting exception.
Native dialog semantics isolate the background and trap focus; support close,
Escape, backdrop click, focus return and reduced motion. Keep the ordinary login
page as the no-JavaScript fallback.

Tabs select public/consultant or staff credential forms immediately. Submit normal
CSRF-protected POSTs to the existing throttled login endpoints. Preserve the configured
staff identifier: email for the local driver, staff number for MBSP. Public users
use email as username. Do not add an alternative authenticator or change account
permissions. Link public recovery and registration below password fields. Staff
credential recovery and staff-account provisioning remain administrator-managed;
show truthful help instead of inventing staff self-registration/reset endpoints.

## Consequences

The idle carousel always presents three complete opaque boxes. Authentication,
validation, throttling and redirects remain with the established controllers.
The modal is a guest entry surface and does not bypass identity boundaries.
