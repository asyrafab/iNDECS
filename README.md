# iNDECS

**iNDECS — Intergrated Development Control System** (**Sistem Bersepada Kawalan Pembangunan**) is MBSP's configurable platform and main portal for development-control applications. It is one shared core with four departmental modules (Perancang, Bangunan, Kejuruteraan, Landskap), and the identity provider for a family of cooperating systems: AIRS (AI Review Studio) for plan review and the Esri E-Plan Submission System for spatial submissions.

The product vision is in [docs/OVERVIEW.md](docs/OVERVIEW.md). The binding design rules are in [AGENTS.md](AGENTS.md) and [docs/DESIGN-DIRECTION.md](docs/DESIGN-DIRECTION.md). The current programme, which brings **iNDECS Bangunan** forward and builds the core alongside it, is documented in [docs/bangunan/](docs/bangunan/README.md).

## Current capability

The repository contains the first working slice of the **Permohonan Kebenaran Merancang (PKM)** module. Implementation decisions and discovery items for this slice are in [docs/PKM-FIRST-SLICE.md](docs/PKM-FIRST-SLICE.md).

- PKM dashboard and searchable application list
- Validated PKM registration form
- Automatic yearly references such as `PKM/2026/00001`
- Shared applicant and consultant records with submission snapshots
- Shared site and multiple cadastral-lot records
- PKM-specific land-use, density, plot-ratio, unit, storey and height fields
- Multiple development components
- First workflow transition from registration to completeness review
- Session authentication with active-user, department and PKM-role access controls
- Configurable required-document checklists by PKM category and type
- Private document storage with SHA-256 checksums and immutable version history
- Officer verification for every checklist item
- Return-for-amendment and resubmission cycles with preserved review history
- Automatic officer assignment and five-working-day completeness due date
- Immutable stage history, audit records and optimistic row versioning
- Responsive server-rendered interface in Bahasa Melayu

The model keeps shared application data separate from `pkm_applications` and `pkm_components`, so the Bangunan, Kejuruteraan and Landskap modules reuse the same core.

## Direction (decided 2026-09-18)

- **Database:** PostgreSQL 16 with PostGIS, pgvector and pg_trgm — **implemented** as of this commit. `docker-compose.yml` provisions the cluster locally, with `indecs` and `airs` databases created side by side per docs/bangunan/11-indecs-core-roadmap.md. Route (`/pkm` → `/perancang`) and table (`pkm_*` → `app_pkm_*`) naming was also reconciled to `docs/DESIGN-DIRECTION.md`; PHP class names were left as-is since they name the PKM application type, which stays as-is per `AGENTS.md`.
- **Identity:** iNDECS issues OpenID Connect for the family; staff via the MBSP staff API or Active Directory, public users via shadow accounts and magic links. Not yet implemented.
- **Core capabilities** (workflow engine, RBAC, fees and payments, notifications, SLA, letters, units registry, domain events) are built in `core` in the order iNDECS Bangunan needs them. PKM migrates onto the workflow engine as the proof of generality. Not yet implemented.
- **Visual direction:** Calm Intelligence, shared with AIRS.
- **Engineering hygiene** (repositories, CI against PostgreSQL, secrets management, staging, tested backups) precedes module work. Git history and the PostgreSQL move are done; CI, staging and backups are not. See `docs/bangunan/15-engineering-hygiene.md`.

## Local setup (current slice)

Requirements:

- PHP 8.3 or newer, with the `pdo_pgsql` and `pgsql` extensions enabled
- Composer 2
- Docker Desktop (for the local PostgreSQL/PostGIS cluster)
- PHP extensions: mbstring, OpenSSL, cURL, fileinfo and ZIP

```powershell
docker compose up -d --build   # first run builds the postgis+pgvector image
composer install
Copy-Item .env.example .env
# Set INDECS_DEMO_PASSWORD in .env to a strong local secret before the first seed.
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000`.

Test suite runs against a dedicated `indecs_test` database in the same cluster (see `phpunit.xml`); create it once with `docker exec indecs_postgres psql -U indecs -d indecs -c "CREATE DATABASE indecs_test;"` followed by the three `CREATE EXTENSION` statements in `docker/postgres-init/01-extensions-and-databases.sql`.

The current interface does not require a frontend build. CSS and JavaScript are maintained as small, first-party assets under `public/`.

## Verification

```powershell
php vendor/bin/pint --test
php artisan test
php artisan view:cache
```

## Important deployment note

This is an internal pilot slice, not a production release. Local authentication and PKM role/department authorization are implemented, but MBSP single sign-on, document malware scanning, production object storage, key management, retention rules, backup policy, and ArcGIS credentials must be implemented before the application is exposed beyond a controlled development environment. The programme documentation under `docs/bangunan/` sets out how and when each is delivered.
