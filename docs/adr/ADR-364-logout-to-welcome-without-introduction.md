# ADR-364: Return logout to the welcome page without the introduction

Date: 2026-11-06. Status: Accepted.

## Decision

Public, email-link guest and staff logout return to the main `home` route (`/`).
After invalidating the authenticated session and regenerating its CSRF token,
flash `skip_welcome_introduction` in the new session for the redirected request.
The welcome page exposes that flag before first paint and the landing script
honours it before starting the introduction. The login dialog stays closed.

## Consequences

Logout retains its existing authentication and session cleanup. The skip applies
only to the immediate return; ordinary guest visits retain the introduction.
No query parameter or persistent browser preference is required.
