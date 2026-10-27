# ADR-304: Public registration, Google and email access

Date: 2026-09-21. Status: Accepted.

## Context

The user requires MBSP authentication for staff/officers and three public entry
methods: registered credentials, Google accounts, or one-time access with email
communications. The current application has officer PKM processing, but no public
application-submission form. This increment implements public identity, not new
department workflows. Public submissions and status correspondence remain Phase C.

## Decision

Retain one user registry and session guard with explicit account_type staff,
public or guest. Existing users default to staff. Public provisioning always
sets a public role, no department and no staff number. Staff access explicitly
requires staff type. Staff records cannot be authenticated, reset or linked from
public endpoints.

Use Laravel Socialite for stateful Google OAuth. Require a verified Google email
and bind by Google's immutable subject. Never automatically link an existing
email account to Google. Linking is an explicit action by the authenticated,
verified public account owner. Store no Google access/refresh token.

Registration requires email verification; password reset and email login use
hashed, short-lived, single-use tokens in core.public_access_tokens. A GET displays
a confirmation form, and a CSRF-protected POST consumes the token under a row lock.
This avoids email scanners consuming login links. Tokens are purpose-bound and
invalidate older links of the same purpose. Generic responses reduce enumeration.

One-time access creates a guest/shadow record only, with an unusable random local
password and has_password false. Email possession authenticates the guest, who
may subsequently claim a registered account by setting a password. No registration
is required for this entry method. Business submission forms must attach ownership
to this identity, never infer it by matching party email addresses.

## Consequences

Google requires an External Web application OAuth client and a configured callback.
Email must use a real SMTP service in production; local Mailpit captures messages.
Public registration requires email verification; phone OTP remains a later
programme requirement. Public forms, submission receipts and case correspondence
are not yet implemented and are not advertised as available in the portal.
