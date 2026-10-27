# ADR-301: Reproducible PostgreSQL verification

Date: 2026-09-21. Status: Accepted.

## Context

ADR-202 and ADR-207 require PostgreSQL development and tests. Existing setup
retains SQLite defaults and requires manual creation of the test database.
RefreshDatabase can rebuild a database selected through environment overrides.

## Decision

Use PostgreSQL by default and remove the SQLite connection and creation hook.
Provision `indecs_test` with PostGIS, vector and pg_trgm through an idempotent
psql script shared by fresh and existing Compose installations. Expose the local
database on loopback only. Keep existing application data and volumes intact.

Before feature-test database refresh or the migration verification script, check
the testing environment and Laravel-resolved connection, including URL options.
Allow only PostgreSQL database `indecs_test`, reject read/write splitting and
connection options that could override the PostgreSQL target. Confirm the actual
server database name before destructive verification.

Provide a GitHub Actions workflow as the locally reviewable implementation of
document 15's preferred host. This does not decide ICT hosting policy or enable
remote branch protection. If ICT selects another host, port the same commands.

## Consequences

Tests deliberately fail closed on other database names. Parallel database suffixes
are not supported yet. Local Compose credentials remain development-only; runtime
privilege separation and schema migration require a subsequent ADR. CI and local
database checks must pass before this increment is considered verified.
