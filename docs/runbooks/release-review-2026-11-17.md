# Portal, identity sandbox and dependency release review

Date: 2026-11-17. Branch: `codex/review-portal-identity-security`.

## Review scope

The starting local commit is `fdf7437`. Review the changes after that commit rather than the accumulated difference from GitHub main.

- `5c399e7`: portal draft cards, document-dialog motion and held removal confirmation; includes the existing four JavaScript tests in hosted CI.
- `0fee293`: isolated MyDigital ID and email OTP sandbox, additive identity tables, synthetic OIDC tests and PostgreSQL concurrency tests. Default-off flags, array mail and real-provider restrictions remain in place.
- `2bdd96a`: mechanical Pint formatting in 98 existing PHP files, isolated from the feature and dependency changes because the full-repository formatting check initially failed.
- Dependency follow-up: only the six packages in the table below are updated, without broadening Composer constraints or running Composer application scripts.

## Dependency remediation

The live initial Composer audit reported nine advisories across six packages. A targeted update with `--with-dependencies --minimal-changes` selected these compatible versions; no other installed package changed in this follow-up.

| Package | Before | After |
|---|---|---|
| laravel/framework | 13.39.0 | 13.42.0 |
| phpseclib/phpseclib | 4.0.1 | 4.0.3 |
| symfony/http-foundation | 8.1.9 | 8.1.11 |
| symfony/http-kernel | 8.1.1 | 8.1.11 |
| symfony/mime | 8.1.0 | 8.1.10 |
| symfony/routing | 8.1.0 | 8.1.11 |

`composer audit --locked --format=json --no-cache` now reports no advisories or abandoned packages. `npm audit --json` reports zero vulnerabilities. These are known-advisory checks, not a comprehensive security audit or permission to activate the identity pilot.

## Verification

- Focused identity, OIDC and real PostgreSQL lock-contention tests before the dependency update: 43 passed, 256 assertions.
- JavaScript tests: all 23 AMIRA and removal-confirmation tests passed. Hosted CI now includes both CommonJS and ESM test files.
- Full-repository Pint check after mechanical fixes: passed.
- Composer manifest validation: passed.
- Frontend production build: passed.
- Full PostgreSQL regression after the dependency update: 840 tests, 838 passed, two skipped, 4,083 assertions and zero failures.
- PostgreSQL extensions and fresh/rollback/reapply/seed migration round-trip: passed on guarded `indecs_test` only.
- `git diff --check`: passed.

## Hosted review boundary

The fetched `origin/main` is `5e6ff0e`, an ancestor of the starting local commit. There are 127 pre-existing local commits between it and `fdf7437`. The owner subsequently authorised reconciliation of that backlog and preparation for a GitHub PR. The review branch retains those commits and the four follow-up commits without rewriting main or changing their history. The PR scope is the complete accumulated platform change, not only the identity sandbox. See the [reconciliation record](backlog-reconciliation-2026-11-17.md). Hosted CI results must be checked on the PR before merging.

No operational database migration, real MyDigital ID connection, real email delivery, production activation or merge is part of this review.
