# 16. Decision Log (Version 2)

Version 1 records ADR-001 to ADR-009 are in `archive/v1-standalone/08-decision-log.md`. Each entry below states which version 1 record it supersedes or confirms.

---

## ADR-201: The Bangunan modernisation is delivered as the iNDECS Bangunan module

Date: 2026-09-18. Status: Accepted. Supersedes ADR-001 (Django monolith).

**Context.** iNDECS is the council's intended platform for all development-control applications, built by the same developer, with binding design documents that already define the Bangunan module. A standalone system would duplicate the platform core and leave two application registries.

**Decision.** Build Bangunan as a department module of iNDECS in Laravel, following `AGENTS.md` and `docs/DESIGN-DIRECTION.md`. The module, its public services and all documents use the name **iNDECS Bangunan**. The earlier working name is retired everywhere, including as a public brand.

---

## ADR-202: PostgreSQL 16 with PostGIS as the platform database

Date: 2026-09-18. Status: Accepted. Supersedes ADR-002 (strangler on shared SQL Server) and ADR-004 (schema `eb`).

**Context.** iNDECS and AIRS run on SQLite in development with no production database chosen. Options: SQL Server (coexists with legacy and the WiseCore suite; Laravel driver lags), MySQL (weak spatial), PostgreSQL (best Laravel fit, PostGIS, `pgvector`, zero licence). The programme owner accepts a migration for long-term benefit.

**Decision.** One PostgreSQL cluster; iNDECS one database with schemas per area; AIRS its own database. Legacy `MPSP_Bangunan` is synced read-only nightly during transition, migrated once at cut-over, then retired. No dual-writing.

---

## ADR-203: iNDECS is the identity provider for the system family

Date: 2026-09-18. Status: Accepted. New.

**Decision.** iNDECS issues OpenID Connect identity. Staff authenticate through the MBSP staff API (moved from AIRS) or Active Directory when available. Public identity uses the AIRS shadow-account and magic-link design, lifted into iNDECS core. AIRS becomes a relying party. E-Submission accounts are linked by identity number unless ArcGIS Portal can federate with iNDECS.

---

## ADR-204: Versioned APIs between all systems; every system runs alone

Date: 2026-09-18. Status: Accepted. New.

**Decision.** iNDECS, AIRS and E-Submission cooperate through versioned REST APIs and signed webhooks with retry and replay. No shared databases. iNDECS holds the record; AIRS reviews; E-Submission converts spatial data. Each continues operating when another is down.

---

## ADR-205: AIRS `BUILDING_PLAN` is the plan-review path; iNDECS internal AI covers analysis and reporting

Date: 2026-09-18. Status: Accepted. Supersedes ADR-006 (self-hosted CPU-first tiers).

**Decision.** Plan and document review, classification and extraction run in AIRS through a Bangunan rule pack. Analysis across applications, working paper drafting, summaries and search run in an iNDECS internal AI services layer sharing the AIRS `AiProvider` contract.

---

## ADR-206: Cloud-hosted AI now, self-hosted when infrastructure allows

Date: 2026-09-18. Status: Accepted. Supersedes the hosting part of ADR-006.

**Context.** MBSP has no self-hosting infrastructure for models. AIRS already uses a cloud provider behind a provider-neutral contract.

**Decision.** Cloud models under a data-handling policy: departmental approval and PDPA review before real documents are sent, minimisation and redaction, provider with acceptable data-processing terms, provider identifiers confined to audit tables. Migration to self-hosted models through the planned local provider and task router when GPU capacity is procured.

---

## ADR-207: Engineering hygiene is a Phase A prerequisite

Date: 2026-09-18. Status: Accepted. New.

**Decision.** Repositories with history, secrets purged and rotated, Docker-based environments, PostgreSQL in tests, CI on every pull request, staging environment, tested backups, onboarding runbook and handover pack are completed before Bangunan module code is written. Details in document 15.

---

## ADR-208: AIRS creative direction is the UI reference for the family

Date: 2026-09-18. Status: Accepted. New.

**Decision.** The AIRS creative direction ("Calm Intelligence": navy structure, teal accent, soft teal ground, white cards, six semantic status colours) is the visual reference for iNDECS UI. Shared tokens and components are published as a package. The version 1 mockups are redrawn accordingly. The iNDECS `AGENTS.md` and `docs/DESIGN-DIRECTION.md` were rewritten on 2026-09-18 to replace the Modernist single-red-accent rules with this direction, so they remain the binding specification for the platform.

---

## ADR-210: Bangunan application type codes

Date: 2026-09-18. Status: Accepted. New.

**Decision.** `PB` pelan bangunan penuh, `PBK` Pelan Tambahan / Ubahsuai Kecil, `PBS` Pelan Bangunan Sementara and Permit Sementara Seksyen 19, `PMT` permits (meletak bahan binaan, perancah, runtuh, tukar guna), `TEL` telco structures and renewable permits. All under department `JBANG`. Reference format `<TYPE>/YYYY/NNNNN`.

---

## ADR-209: Bangunan-led core sequencing

Date: 2026-09-18. Status: Accepted. Supersedes version 1 phase plan.

**Context.** Management brought iNDECS Bangunan forward.

**Decision.** Core capabilities are built in the order Bangunan needs them, generically, in `core`. The original PKM migration during Phase B is superseded by ADR-211; PKM now follows Bangunan in module rollout Phase 2. Phases A to E in document 11.

---

## ADR-211: Bangunan first, PKM second

Date: 2026-10-27. Status: Accepted. Supersedes the PKM migration timing in ADR-209.

**Decision.** Module rollout Phase 1 delivers Bangunan through technical milestones A to D. Module rollout Phase 2 delivers PKM (Perancang), starting in milestone E only after Bangunan acceptance and operational handover. PKM is the next module before Kejuruteraan and Landskap. Milestones A to E remain technical milestones, distinct from module rollout numbering.

**Consequences.** Remove PKM workflow migration as a Phase B dependency. Validate the shared engine against Bangunan's five families first. Maintain existing PKM functionality and shared-platform regression checks during Bangunan delivery. In Phase 2, configure and migrate PKM, reconcile records and history, verify permissions and obtain end-to-end acceptance. Estimate PKM scope and dates separately; the indicative 21-month Bangunan plan is not a PKM completion commitment.

---

## Confirmed from version 1

- ADR-003 Unique ID scheme: confirmed, with the units registry moved into iNDECS core (document 11, C7).
- ADR-005 OWASP ASVS Level 2 baseline and internal e-approval with QR: confirmed, implemented in core letters.
- ADR-007 Enforcement handover boundary: confirmed.
- ADR-008 MBSPpay as the payment gateway: confirmed; AIRS billing design becomes the core pattern.
- ADR-009 Documentation language: confirmed.

## Pending decisions

| ID | Decision | Owner | Needed by |
|---|---|---|---|
| PD-201 | PostgreSQL permitted by MBSP ICT | ICT | Phase A month 1 |
| PD-202 | Repository host: GitHub organisation or on-premises GitLab | ICT | Phase A week 1 |
| PD-203 | Active Directory availability for staff SSO | ICT | Phase A month 1 |
| PD-204 | Cloud AI approval and PDPA data-handling review | Programme owner, legal | Phase A |
| PD-205 | AI provider and data-processing agreement | Technical lead, ICT | Phase A month 2 |
| PD-206 | Additional developer | Programme owner | End of Phase A |
| PD-207 | E-Submission change request for webhooks and iNDECS reference field | Programme owner, Esri | Phase B month 6 |
| PD-208 | ArcGIS Portal federation with iNDECS OIDC | Esri via programme owner | Phase C |
| PD-209 | OSC3Plus integration scenario | Programme owner, state OSC unit | Phase C |
| PD-210 | OSC reference uniqueness across departments | Product owner | Phase A month 2 |
| PD-211 | Letter template list from 165 Crystal reports | Product owner | Phase B month 2 |
| PD-212 | Delegated authority thresholds for PBK | Pengarah | Phase B month 5 |
| PD-213 | Piagam Pelanggan durations per family | Product owner | Phase B month 5 |
| PD-214 | Bangunan officer reference group for rule confirmation | Pengarah | Phase B |
