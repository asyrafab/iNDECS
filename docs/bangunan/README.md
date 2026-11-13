# iNDECS Bangunan Programme

> **NADI alignment — 9 November 2026:** [Current direction](../eosc/nadi-direction.md): **NADI > eOSC**. iNDECS registers papers under eOSC for assigned meetings and communicates with NADI, the application used within meetings. Other meeting types may become sibling NADI modules later. The committee remains Jawatankuasa OSC. Earlier all-inside-iNDECS meeting descriptions are historical where they conflict; integration implementation remains to be designed.

> **AI ecosystem alignment — 9 November 2026:** [Current roles and integration boundaries](18-ai-ecosystem-alignment.md). AMIRA remains application assistance; MATRIX is the planned document specialist; MINDA is internal intelligence. Existing authority, free-precheck and payment gates remain.


> **Integration policy update — 2026-11-04:** [ADR-352](../adr/ADR-352-indecs-airs-submission-and-precheck.md) and the [shared contract](../integration/indecs-airs-contract.md) govern the single iNDECS submission journey. Free applicant precheck is bounded checklist/file/text/local-OCR preparation with no paid AI; payment clears before formal acceptance, then AIRS technical review runs. iNDECS owns operational workflow and decisions; scores are advisory. Fees and correction-round charging remain undecided. Conflicting earlier tariffs, standalone AIRS operational ownership and paid AI intake auto-fill before submission are superseded. Historical milestones are not implementation claims.

Modernisation of Jabatan Bangunan's legacy Sistem Bangunan 2.0 as the **iNDECS Bangunan** module, with the iNDECS core built alongside it, AIRS as the plan-review sub-system and the Esri E-Plan Submission System as the spatial sub-system.

Status updated 2026-11-04: Bangunan registration, scoped case access, configured workflow, department-confirmed document catalogues, scanned private document revisions, officer verification and correction/resubmission are implemented in the shared iNDECS application (ADR-309/310). Fee assessment/payment, public formal submission, AIRS technical review and live AMIRA conversation remain future increments. AMIRA is an independent backend under ADR-353; Bangunan currently uses the shared local guidance panel. See the [registration runbook](../runbooks/bangunan-registration.md), [document-verification runbook](../runbooks/bangunan-document-verification.md) and [AMIRA boundary](../integration/amira-backend.md).

| # | Document | Purpose |
|---|---|---|
| 10 | [Platform Landscape](10-platform-landscape.md) | The four systems, what each owns, the binding decisions |
| 11 | [iNDECS Core Roadmap](11-indecs-core-roadmap.md) | Core capabilities, PostgreSQL and security baseline, Phases A to E; concurrent module delivery (ADR-212) |
| 12 | [Identity and Integration Contracts](12-identity-and-integration-contracts.md) | iNDECS as identity provider; contracts with AIRS, E-Submission, OSC3Plus, MBSPpay and council departments |
| 13 | [iNDECS Bangunan Module Specification](13-indecs-bangunan-module-spec.md) | Five families, workflow, extension tables, legacy migration, functions, phases |
| 14 | [AIRS Building Plan and Internal AI Services](14-airs-building-plan-and-ai-services.md) | Activating AIRS `BUILDING_PLAN`; iNDECS internal AI; cloud-now, self-hosted-later policy |
| 15 | [Engineering Hygiene](15-engineering-hygiene.md) | Repositories, CI, environments, secrets, handover, sole-developer controls, Phase A checklist |
| 16 | [Decision Log](16-decision-log.md) | ADR-201 onward and pending decisions |
| 17 | [Glossary](17-glossary.md) | Systems, legal references, domain terms |

Other department projects keep their own folders alongside this one (ADR-212): the NADI/eOSC meeting direction is in [`../eosc/`](../eosc/README.md); `../perancang/`, `../kejuruteraan/` and `../landskap/` are added as those projects start.

Architecture decision records for code-level decisions go in `../adr/`, one file per decision, numbered from ADR-301 so they never collide with the programme-level records in the decision log.

Legacy reference material (functional description of Sistem Bangunan 2.0 and the static teardown of its binary and schema) is held by the technical lead and is not committed here because the teardown documents how the legacy database credentials can be recovered.
