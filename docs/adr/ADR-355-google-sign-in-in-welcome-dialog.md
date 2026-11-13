# ADR-355: Google sign-in in the welcome login dialog

Date: 2026-11-04. Status: Accepted.

## Context

The landing-page login dialog is the main entry surface. Google OAuth is configured and its existing route works, but the dialog omits the option that is present on the older standalone public login page.

## Decision

Show `Teruskan dengan Google` after the password submit button in the public/consultant tab, using the existing `portal.google` route and shared secondary-button styling. It is an ordinary navigation link and does not submit or validate the password form. Show a disabled availability button if either OAuth setting is missing. Do not add Google sign-in to the staff tab or expose configuration/secrets to the browser. The callback remains the existing technical OAuth route; no new login page is introduced.

## Consequences

Retain the dialog's existing keyboard/focus behaviour, tab selection, responsive portrait and token-based styling. Google authentication and account-linking rules are unchanged.
