# iNDECS Bangunan Programme

Modernisation of Jabatan Bangunan's legacy Sistem Bangunan 2.0 as the **iNDECS Bangunan** module, with the iNDECS core built alongside it, AIRS as the plan-review sub-system and the Esri E-Plan Submission System as the spatial sub-system.

Status: planning agreed 2026-09-18. No module code written yet. Phase A (foundation and engineering hygiene) is the next step.

| # | Document | Purpose |
|---|---|---|
| 10 | [Platform Landscape](10-platform-landscape.md) | The four systems, what each owns, the binding decisions |
| 11 | [iNDECS Core Roadmap](11-indecs-core-roadmap.md) | Core capabilities in Bangunan-led order, PostgreSQL and security baseline, Phases A to E |
| 12 | [Identity and Integration Contracts](12-identity-and-integration-contracts.md) | iNDECS as identity provider; contracts with AIRS, E-Submission, OSC3Plus, MBSPpay and council departments |
| 13 | [iNDECS Bangunan Module Specification](13-indecs-bangunan-module-spec.md) | Five families, workflow, extension tables, legacy migration, functions, phases |
| 14 | [AIRS Building Plan and Internal AI Services](14-airs-building-plan-and-ai-services.md) | Activating AIRS `BUILDING_PLAN`; iNDECS internal AI; cloud-now, self-hosted-later policy |
| 15 | [Engineering Hygiene](15-engineering-hygiene.md) | Repositories, CI, environments, secrets, handover, sole-developer controls, Phase A checklist |
| 16 | [Decision Log](16-decision-log.md) | ADR-201 onward and pending decisions |
| 17 | [Glossary](17-glossary.md) | Systems, legal references, domain terms |

Architecture decision records for code-level decisions go in `../adr/`, one file per decision, numbered from ADR-301 so they never collide with the programme-level records in the decision log.

Legacy reference material (functional description of Sistem Bangunan 2.0 and the static teardown of its binary and schema) is held by the technical lead and is not committed here because the teardown documents how the legacy database credentials can be recovered.
