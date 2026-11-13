# ADR-357: Separate Google login visually from password login

Date: 2026-11-04. Status: Accepted. Refines ADR-356 for the welcome dialog.

## Decision

The owner requests the Google logo outside the outlined button, at the same left-hand alignment, and a visible `atau` divider between password and Google login. Use a separate decorative logo holder on a white round ground, followed by the outlined Google action. Preserve the logo's existing visual inset and colours. The divider uses token-based horizontal rules and the current login palette. Keep the Google action as an ordinary link to the existing public OAuth route.

## Consequences

`.google-login-option`, `.google-login-logo` and `.welcome-login-divider` live in the shared CSS class layer. No authentication, staff-tab, field-validation or theme-branch changes. The existing standalone fallback retains its original combined Google button.
