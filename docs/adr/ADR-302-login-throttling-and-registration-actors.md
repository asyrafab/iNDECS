# ADR-302: Login throttling and registration actors

Date: 2026-09-21. Status: Accepted.

## Context

The Phase A security baseline requires a lockout after five failed attempts and
attributable audit records. Current local login has no limiter and registration
omits the officer from its history and audit entries.

## Decision

Use Laravel's rate limiter with a hashed, normalised email-plus-IP key. Five
failures in 60 seconds block further attempts until expiry, including correct
credentials. Successful authentication before the threshold clears the counter.
Use the same failure path for inactive or nonexistent accounts. This is the
interim local-login control; shared staff identity, MFA and broader abuse
controls remain separate Phase A work.

Require an explicit User actor in the registration service and pass the
authenticated officer from the controller. Store that actor on both records
inside the existing registration transaction.

## Consequences

The limiter uses the configured cache backend; production must use a shared
backend across workers. Registration callers must supply an actor. Existing
historical rows are not backfilled with guessed identities.
