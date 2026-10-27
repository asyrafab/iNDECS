# ADR-303: MBSP staff API authentication

Date: 2026-09-21. Status: Accepted.

## Context

The user supplied API LOGIN STAFF (1).pdf and the Billing System as the staff
authentication reference. The PDF specifies Basic Auth, form encoding, nostaff,
password and a success/data response. StaffDirectoryService in Billing System
clarifies the dynamic API-key field and MD5 password transport mode.

## Decision

Implement StaffAuthenticator with an MBSP HTTP adapter. Default staff login uses
staff number and the MBSP password. Local demo login is an explicitly selected
local/testing-only driver and never a fallback after API failure. The API request
uses HTTPS with certificate validation, no redirects, bounded timeouts and no
automatic retries. MD5 is only the legacy API transport format, never local
password storage. Always hash the entered password in MD5 mode, without treating
a 32-character password as an already-hashed credential. Raw mode is configurable.

Require a pre-linked unique staff_number on an active iNDECS user. Do not link by
API email, auto-create privileged users or infer department/role from a profile.
Existing iNDECS authorisation remains decisive. Match returned NOSTAFF to the
submitted number. Accept only FLAGPASSWORD valid; expired passwords are denied
with a message to change them through MBSP. No password-changing endpoint is
documented, so none is invented. Disable persistent remember-me for MBSP login.

Keep service credentials in environment configuration. Never log request or
response bodies, persist staff passwords, or expose transport exceptions.

## Consequences

Login errors distinguish an MBSP credential rejection from a local access denial.
The latter is shown only after successful MBSP authentication, so unauthenticated
requests do not disclose whether a local staff account exists. Neither message
includes upstream profile data or credentials.

An administrator must link approved users to staff numbers. API unavailability
blocks new logins without ending existing sessions. Live verification requires
an authorised staff login; automated tests use HTTP fakes. This implements the
staff credential source, not the future OIDC provider or shared RBAC.
