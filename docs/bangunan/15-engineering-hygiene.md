# 15. Engineering Hygiene

Version 2.0, 2026-09-18. Decision record: ADR-207.

## 1. Why this document exists

On 2026-09-18 the state of the in-house systems was: iNDECS with an empty git folder, AIRS with one commit and a month of uncommitted work, both on SQLite with `.env` files containing live keys on disk, no CI, no deployment artefacts, and one person who knows everything. None of this is unusual for a prototype. All of it is unacceptable for a system that will issue statutory letters. This document sets the controls that make the situation not recur, and it is a prerequisite for Phase A, not a later improvement.

## 2. Source control

- **Host.** A private GitHub organisation for MBSP in-house systems now, since AIRS already documents GitHub use. If MBSP ICT policy requires code to stay on premises, a self-hosted GitLab Community Edition or Gitea in the data centre, with the same workflow. Decide in Phase A week 1; the workflow below is host-neutral.
- **Repositories.** `indecs` (platform including all department modules, as one Laravel monolith), `airs` (standalone), `mbsp-ui` (shared design tokens and Blade components following the AIRS creative direction, published as a Composer package), `mbsp-identity-client` (OIDC client package used by AIRS and any future relying party), `indecs-docs` is not separate: documentation lives in each repository's `docs/`.
- **Initial import.** Both codebases committed in their current state on day one, then `.env` and any secrets removed from history with a rotation of every key that was ever on disk. Migrations that only work on SQLite are fixed before the PostgreSQL move.
- **Branching.** Trunk-based: short-lived branches, pull requests into `main` even for a solo developer, because the pull request is where CI runs and where the AI code review runs. `main` is protected: no direct pushes, CI must pass.
- **Commits.** Conventional Commits (`feat(bangunan): ...`, `fix(core): ...`) so changelogs are generated. Semantic version tags per release, as AIRS already does (`v0.7.0-beta.1`).
- **Reviews.** Every pull request gets an automated review pass plus a self-review checklist. When a second developer joins, human review becomes mandatory for `core` and for anything touching letters, payments or identity.

## 3. Continuous integration

GitHub Actions (or GitLab CI) on every pull request and on `main`:

1. `composer install`, `npm ci`.
2. Static analysis: Laravel Pint check, Larastan at level 6 or higher, `composer audit`, `npm audit`.
3. Tests: PHPUnit against a PostgreSQL 16 service container with PostGIS, not SQLite, so the tests exercise the production database. Python worker tests for AIRS.
4. Migrations: fresh migrate plus seed, then a migrate rollback and re-migrate, so migrations stay reversible.
5. Build: Vite production build; Docker image build and Trivy scan.
6. Knowledge validation for AIRS: `plan-review:validate-knowledge` on rule packs.
7. On tag: publish images to the registry and generate the changelog.

A failing pipeline blocks merge. Coverage is reported but not gated initially; the target is that every service class has tests.

## 4. Environments and deployment

- **Local.** Docker Compose with PostgreSQL 16 and PostGIS, Redis, MinIO, Mailpit, and the Python worker. One command sets up a working environment from a clean machine. No SQLite anywhere in the application.
- **Staging.** One VM in the MBSP data centre, same compose stack, seeded with a scrubbed copy of production. Every release is rehearsed here and accepted by the product owner.
- **Production.** Application VM with web, queue workers, scheduler, Redis and MinIO in containers; PostgreSQL on its own VM or the ICT-managed cluster. Images pinned by digest. Zero-downtime deploys via container swap. Rollback is redeploying the previous tag.
- **Configuration.** Environment variables injected from a secrets store (Vault if ICT has one, otherwise Docker secrets on a locked-down host). Separate database roles per environment with least privilege. `APP_KEY` and OIDC signing keys rotated on a schedule and on any suspected exposure.
- **Observability.** Structured JSON logs shipped to a central store, Prometheus metrics, Grafana dashboards, self-hosted error tracking (GlitchTip), alerts on failed jobs, stuck queues, webhook dead-letters and certificate expiry.
- **Backups.** PostgreSQL nightly base backup plus WAL archiving; MinIO versioning plus nightly replication; quarterly restore drill with a written record.

## 5. Documentation discipline

- `AGENTS.md` and `docs/DESIGN-DIRECTION.md` in iNDECS remain binding and are updated in the same pull request as any change that affects them.
- Architecture decision records in each repository under `docs/adr/`, one file per decision, numbered, never edited after acceptance, superseded by a new record.
- Runbooks for: deploy, rollback, restore, rotate secrets, add a department module, add an AIRS module, onboard a developer.
- A **handover pack** kept current: system map, credentials location (not the credentials), vendor contacts, contract references, open decisions. Reviewed monthly.

## 6. Reducing the sole-developer risk

Architecture and tooling reduce the cost of losing the one person who knows the systems; they do not remove it. The controls below are ordered by effect.

1. **Ask for a second developer** in the proposal to management, with a named consequence if refused (Phase D and E scope drops, public portal moves after internal stability).
2. **Pair with an MBSP ICT counterpart** from Phase A: they attend the deploy, hold a copy of the handover pack, and can run the restore drill without the developer present.
3. **Thirty-minute onboarding test.** Every quarter, someone who has not touched the code follows the onboarding runbook on a clean machine and gets a working local environment. If it takes longer, the runbook is fixed.
4. **Automate everything that happens more than twice.** Setup, deploy, backup, restore, seed, knowledge validation.
5. **Keep the boring stack boring.** Laravel conventions, no exotic packages, no clever abstractions that only the author understands. The AIRS `final class` and `declare(strict_types=1)` discipline is kept.
6. **Write the decision before the code.** The decision log is how a successor learns why, not only what.
7. **Vendor relationships documented.** Esri Malaysia and WiseCore contacts, contract references and change-request procedures in the handover pack, so integration conversations survive a staff change.

## 7. Phase A hygiene checklist

Complete before any Bangunan module code is written.

- [ ] Repository host decided with ICT; organisation created
- [ ] iNDECS and AIRS committed; secrets purged from history; every exposed key rotated
- [ ] Docker Compose local environment for both; SQLite removed
- [ ] PostgreSQL migrations passing for both; PostGIS and `pgvector` enabled
- [ ] CI pipeline green on `main` for both repositories
- [ ] Staging VM provisioned; first deploy from a tag
- [ ] Backup and restore drill completed once and recorded
- [ ] Onboarding runbook tested by a non-author
- [ ] Handover pack v1 written and held by ICT counterpart
- [ ] ADR directory created with the version 2 decisions imported

## Open questions

1. GitHub permitted for council source code, or on-premises hosting required. Owner: ICT. Needed by: Phase A week 1.
2. Existing secrets store or WAF in MBSP to reuse. Owner: ICT. Needed by: Phase A month 1.
3. Named ICT counterpart for the pairing arrangement. Owner: programme owner. Needed by: Phase A month 1.
