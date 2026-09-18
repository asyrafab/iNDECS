# 14. AIRS Building Plan Module and Internal AI Services

Version 2.0, 2026-09-18. Decision records: ADR-205, ADR-206. Replaces the version 1 AI strategy.

## 1. Division of AI responsibility

| Concern | System | Reason |
|---|---|---|
| Reviewing submitted plans and documents against requirements; classifying documents; extracting application profiles; evidence-linked findings | **AIRS** | Working engine, versioned rule packs, evidence discipline, officer feedback loop already built |
| Spatial data submission and CAD-to-GIS conversion | **E-Submission** | Vendor scope |
| Analysis across applications, working paper drafting, summaries of technical comments, dashboard narratives, natural-language case search, SLA risk flagging | **iNDECS internal AI services** | Needs the system of record's data, not documents |

Shared principle, inherited from both iNDECS overview section 22 and the AIRS charter: **AI assists, the officer decides.** No AI output changes an application state, issues a letter or rejects a submission. Every output is reviewable, carries a confidence and a source, and its acceptance or correction is logged.

## 2. Hosting policy: cloud now, self-hosted later

MBSP has no infrastructure for self-hosted models today. Policy agreed 2026-09-18:

1. Cloud-hosted models are used through the provider-neutral `AiProvider` contract that AIRS already has and that iNDECS adopts.
2. **Data handling.** Sending applicant documents to a cloud provider requires written departmental approval and a data-handling review under PDPA 2010. Until obtained, only anonymised or approved test material goes to cloud models. This approval is a Phase A discovery task with a named owner.
3. **Minimisation.** Only the pages and fields a task needs are sent, never whole archives. A redaction step masks identity numbers and phone numbers in text before it leaves the network where the task does not need them.
4. **Provider choice.** Any provider with a data-processing agreement acceptable to MBSP, regional data residency where offered, and no training on customer data. The contract is compared across at least two providers before commitment.
5. **Portability.** Provider identifiers stay confined to the `ai_runs` audit table. Prompts, schemas and rule packs are versioned data. This is what makes the later move to self-hosted models a swap.
6. **Migration trigger.** When MBSP can procure GPU capacity, AIRS's planned `LocalAiProvider` and `AiTaskRouter` route cheap tasks local first, then all tasks after a shadow comparison against a gold set. The same router serves iNDECS internal AI.
7. **Budget.** Per-case token caps, caching on request hash, and a monthly AI cost dashboard, as AIRS already designs. AIRS's own estimate of RM3,000 to RM25,000 per month for its volume is the starting figure; Bangunan volume is roughly twenty times LJUP volume, so the budget model must be recomputed in Phase A.

## 3. Activating the AIRS `BUILDING_PLAN` module

AIRS states it plainly: the framework is reusable, the domain knowledge is not. Activation is knowledge work plus one interface.

### 3.1 Rule pack `knowledge/bangunan/rules.v1.json`

Sources, in order of authority: Undang-Undang Kecil Bangunan Seragam 1984 with Penang amendments; MBSP Garis Panduan Pelan Bangunan; Garis Panduan Pelan Tambahan Kecil; MS 1184 for accessibility; SPAH and OTTV requirements; the department's document checklists per family.

Rule shape follows AIRS: permanent `rule_id` (`PB-SUB-001`, `PB-PLAN-014`), stage (`SUB` submission screening, `PLAN` plan review), requirement text in Malay, `applies_when` predicates on family, building type, storeys and use, evaluation method (`DETERMINISTIC`, `AI_TEXT`, `AI_VISION`, `CROSS_DOCUMENT`, `MANUAL`), required evidence types, severity, standard comment.

Expected first pack: roughly 60 to 100 rules. Submission screening rules (document present, title block complete, PSP registration stated, lot matches title) are deterministic or cross-document and give value immediately. Plan-review rules (setbacks, coverage, height, OKU provisions, SPAH sizing) begin as `AI_VISION` and `MANUAL` and move to deterministic as structured data arrives from the Bangunan Borang Jabatan and from E-Submission geometry.

### 3.2 Reference documents

Uploaded per module into AIRS's `reference_documents`: the guideline PDFs above, the fee schedule, the checklist forms. AIRS's reference context service injects relevant text into prompts.

### 3.3 Calibration

The AIRS LJUP experience shows one real case is not enough. Plan: 30 historical Bangunan cases with officer comments, de-identified, keyed as a gold set in Phase B; rule pack calibrated against them before Phase C activation; acceptance rate tracked from `review_finding_feedbacks` monthly. Officer confirmation of every rule's text, applicability and severity by a named Bangunan reference group is the gate for publishing the pack.

### 3.4 OCR

Scanned hardcopy is common for PBK and permits. AIRS's OCR is prepared but not wired. The `OcrProvider` contract, engine comparison (Tesseract versus PaddleOCR, or a cloud OCR API under the same policy), high-DPI tiling for drawings, and confidence routing to manual review are AIRS Phase 4 items that must complete before Bangunan Phase C. Counter scanning in iNDECS feeds the same pipeline.

### 3.5 Interface

Defined in document 12 section 3: create review, status and completion webhooks, findings retrieval, feedback write-back, and a single-document classify-and-extract endpoint for intake auto-fill. AIRS needs token authentication for system clients (currently session-only), module-level configuration of fees and SLA so `BUILDING_PLAN` does not inherit LJUP's RM150 and three-day values, and OIDC login against iNDECS.

## 4. iNDECS internal AI services

A Laravel service layer in iNDECS `app/Services/Ai/`, implementing the same `AiProvider` contract as AIRS so both applications share provider adapters, and eventually a shared Composer package.

| Capability | Input | Output | Phase |
|---|---|---|---|
| Working paper drafting | Application data, confirmed compliance checklist, technical comments, fee summary | Kertas pembentangan with data sections filled by template and narrative sections drafted, each paragraph marked as draft with provenance until edited or accepted | D |
| Technical comment summarisation | SKP5 responses | Short summary with links to originals | D |
| Compliance summary | AIRS findings plus deterministic rule results from Borang Jabatan | Officer-facing checklist grouped by rule then narrative | D |
| SLA risk flagging | Stage, age, family, historical durations | Risk level and predicted breach date per application | D |
| Natural-language case search | Officer question | Structured query over core tables with results and the generated filter shown | D |
| Dashboard narratives | Monthly figures | Plain-language summary for the Pengarah | D |
| Applicant FAQ assistant | Question | Answer grounded in guidelines, fee schedules and UKBS 1984 text stored as embeddings in `pgvector`, with citations, declining when unsure | C |

Data model additions follow the iNDECS overview section 22.4: `ai.model_versions`, `ai.runs`, `ai.suggestions` (entity, field or section, suggested value, confidence, model version, accepted by, accepted at), `ai.feedback_log`. Every suggestion is traceable to a model version and an officer decision.

## 5. Evaluation and governance

- Golden sets: 30 cases for AIRS Bangunan rules; 50 working papers for drafting quality, scored by edit distance and officer acceptance.
- Acceptance rate targets after three months: above 85 percent for form-field extraction, above 60 percent for free-form extraction, majority acceptance of working-paper drafts as starting points.
- Quarterly AI report to the Pengarah: usage, acceptance, cost, errors caught, time saved.
- Feature flags per user group; officers can switch suggestions off per application.
- Bias check across daerah and applicant type.

## Open questions

1. Departmental approval and PDPA data-handling review for cloud processing of real documents. Owner: programme owner with legal. Needed by: Phase A.
2. Provider selection and data-processing agreement. Owner: technical lead with ICT. Needed by: Phase A month 2.
3. Bangunan reference group of officers to confirm rules. Owner: Pengarah. Needed by: Phase B.
4. Digital copies of MBSP guidelines and UKBS 1984 with Penang amendments. Owner: product owner. Needed by: Phase B.
5. Annual application volume per family to size the AI budget. Owner: product owner. Needed by: Phase A.
