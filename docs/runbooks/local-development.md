# Local Development and Verification

Requires local Windows PostgreSQL 18 + PostGIS service (`postgresql-x64-18` on `127.0.0.1:5434`), PHP 8.3+ with pdo_pgsql, pgsql, mbstring, fileinfo, OpenSSL, curl and zip, Composer 2 and Node.js 22. CI uses PHP 8.5. Run commands from the repository root.

## Database Setup

The local PostgreSQL 18 instance (`127.0.0.1:5434`) hosts the consolidated `iNDECS` database (containing `iNDECS` in `public`/`core`, `AIRS` in `airs`, and `MIDR` in `catalogue`/`core`/`application`/`publish`) along with isolated test databases `indecs_test` and `airs_test` with `postgis`, `pg_trgm`, and `btree_gist` enabled.

The local `indecs` credentials are exclusively for local development. This increment does not implement production database roles or secrets management.

## Application Setup

```powershell
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
```

Generate the key only for a new installation; retain an existing application's
key. For a new installation run `php artisan key:generate`. Set
`INDECS_DEMO_PASSWORD` to a local secret before first application seeding.

```powershell
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

The current UI also works directly from the assets under `public/`.

## Verification

```powershell
php artisan config:clear
php vendor/bin/pint --test
php scripts/verify-database.php
php artisan test
composer audit
npm audit
npm run build
```

The verification script checks required extensions, rebuilds and seeds
`indecs_test`, rolls migrations back and re-applies them. All contents of that
dedicated test database are disposable. It checks the resolved connection and
actual server database before rebuilding. Feature tests apply the same guard
before RefreshDatabase. Only PostgreSQL `indecs_test` in environment `testing`
is accepted; split read/write connections and alternate connector targets are
rejected. Environment/URL overrides do not bypass this check.

When running CI without `.env`, supply the same PostgreSQL host, port and local
credentials as `.env.example`. The GitHub workflow prepares `.env` automatically.
Do not run parallel tests yet: suffixed test databases are intentionally rejected.

## Recovery and Remaining Gates

- Engine pipe unavailable: start Docker Desktop, then retry database setup.
- Port conflict: inspect the existing service before changing ports; keep application
  and test configuration consistent with the selected port.
- Missing extensions: re-run the SQL script against the existing Compose service.
- Test safety rejection: clear cached configuration and inspect database variable
  names/targets without sharing credentials. Never disable the guard to run tests.
- Stop with `docker compose down`; retain the named volume.

The local workflow file is prepared but needs an approved remote and a green
hosted run. Larastan, deployable application image scanning, branch protection,
staging, production backup/restore and onboarding by a non-author remain open
Phase A work. This runbook is not evidence that those gates have passed.
