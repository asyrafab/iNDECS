# ADR-312: Public landing page at the existing entry URL

Date: 2026-10-29. Status: Accepted.

## Context

The programme owner requested an iNDECS landing page. The root URL currently
requires authentication and an unused welcome view offers only login links.

## Decision

Render the welcome view for guests at `/`, retaining the `dashboard` route name
and all existing authenticated routing. Keep departmental routes behind their
existing authentication and permission middleware. The landing page uses the
shared tokens, crest-first branding, mark, Lucide icons and appearance controls.

Provide public login, registration, email access and staff login links to existing
routes. Explain the four departments without exposing staff workspaces or linking
to unimplemented services. State that public submission, payments and tracking
are not yet available. Use native details for frequently asked questions.

## Consequences

Guests can understand the service before choosing an access path. Signed-in
users retain their current entry behaviour. No identity, application, workflow,
integration or database contracts change. Verify guest access, protected routes,
authenticated routing and responsive light/dark presentation.
