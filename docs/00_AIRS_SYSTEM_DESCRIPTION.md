# AI Review Studio (AIRS) — System Description

**Document version:** 1.0
**Date:** 11 August 2026
**Application version at time of writing:** `v0.7.0-beta.1` (beta channel)
**Product owner:** Majlis Bandaraya Seberang Perai (MBSP)
**Status:** Beta / controlled pilot

---

## About this document

This is the entry point for AIRS. It describes what the system is, how it works, what has been built, and where it is going.

It is written in two layers. **Part I** is for management and non-technical readers and can be read on its own. **Parts II and III** are for developers, vendors and auditors.

### Documents this supersedes

The following are absorbed into this document and should no longer be circulated as current:

| Document | Reason |
|---|---|
| `31_LATAR_BELAKANG_SISTEM_AIRS.md` | Background and problem statement, now Part I §2–3. Its capability claims are stale — it describes a system with no accounts, roles or workflow, all of which now exist. |
| `32_RINGKASAN_PERKEMBANGAN_AIRS.md` | ~70% restated doc 31. Its unique 36-step build chronology is retained in Appendix C. |
| `33_ANGGARAN_KOS_PEMBANGUNAN_AIRS.md` | Cost estimates, now Part I §8. Retained in the repository as the detailed costing workpaper. |
| `36_SENARAI_FASA_DAN_TUGAS.md` | Phase roadmap, now Part III. Task-level detail lives in `34_CHECKLIST_INDUK_PEMBANGUNAN_AIRS.md`. |
| `AIRS_MANAGEMENT_PRESENTATION_BRIEF.md` | Presentation content, largely restating doc 31. Its governance framing is retained in Part I §3–4 and its outstanding management asks in Part III §19. |

### Documents that remain live

- `34_CHECKLIST_INDUK_PEMBANGUNAN_AIRS.md` — the working task checklist. **This is the authoritative task list.**
- `22_AMIRA_RIVE_SPEC.md` — the AMIRA animation asset contract.
- `21_AIRS_CREATIVE_DIRECTION.md` — brand, visual identity and interface direction.
- `CHANGELOG.md` — release history.
- The numbered technical references `01`–`20`, which remain useful but are older than this document. Where they disagree with this document, **this document wins**. Known divergences are listed in Appendix B.

---

# Part I — Overview

## 1. What AIRS is

AIRS is an AI-assisted technical review system for development plan applications submitted to MBSP. Its first and only active module reviews **Pelan Lampu Jalan Untuk Pembangunan (LJUP)** — street lighting plans.

An applicant submits a package of documents. AIRS extracts, classifies and reads them, checks them against a versioned internal catalogue of MBSP's own requirements, and produces a structured report in which every finding is linked to the specific document and page that supports it. An MBSP officer then reviews those findings item by item and an approval officer signs off.

The assistant persona presented to users is **AMIRA** — *AI Multidocument Interpretation and Review Assistant*.

### What AIRS is not

- It is not an approval system. It does not approve or reject applications.
- It is not a replacement for technical officers.
- It is not a complete One Stop Centre (OSC) system.
- It is not a system of record for MBSP.
- It does not verify payment, stamps, signatures or authenticity without an independent source.

## 2. The problem it addresses

Reviewing an LJUP submission manually requires an officer to open every file individually, work out what each document is, hunt for basic application details scattered across unrelated pages, compare technical values between drawings and reports, check each item against a checklist, and write consistent comments. Much of this is screening work that happens before any real engineering judgement begins.

Six specific difficulties drive the design:

1. **Volume and variety.** A submission arrives as a ZIP or a pile of PDFs and images. Filenames rarely describe contents. Some documents are scans with no text layer.
2. **Scattered information.** Project title, developer, address and reference numbers are seldom on one page.
3. **Cross-checking.** Technical values must agree between documents — in the pilot, lamp count and wattage must match between the street lighting plan and the photometric report.
4. **Inconsistent commentary.** Manual comments vary in structure, terminology and depth between officers and over time.
5. **Traceability.** Every finding must state which document and page supports it. A finding is not credible merely because a model asserted it confidently.
6. **The limits of AI judgement.** Models misread drawings, tables and technical terms. Output is therefore *preliminary findings for officer review*, never a decision.

## 3. Governing principle

> **AI assists. The officer decides.**
> *(AI membantu. Pegawai memutuskan.)*

This is not a slogan; it is enforced in the architecture:

- No model output can set an official status without passing schema validation and an evidence check.
- A requirement cannot be marked fulfilled (`DITUNAIKAN`) without at least one stored evidence record.
- The overall result is computed deterministically in code, never by a model.
- The system never produces a result labelled "approved".
- Document text is treated as untrusted data, never as instructions to the system.

## 4. Who uses it

| Role | Enum value | Responsibilities |
|---|---|---|
| Applicant | `PUBLIC_APPLICANT` | Prepares and submits applications, pays, responds to correction requests, downloads the outcome. May act on behalf of one or more companies. |
| Clerk | `MBSP_CLERK` | Oversees payments, receipts and reconciliation; monitors the application dashboard; assigns district manually when automatic lookup fails. |
| Review officer | `MBSP_OFFICER` | Reviews applications item by item within assigned districts, then submits to the approval officer. |
| Approval officer | `MBSP_APPROVAL_OFFICER` | Approves reviewed applications. Approval triggers digital signing of the outcome letter. |
| System administrator | `ADMIN` | Manages accounts, roles and passwords. Also holds full review and approval powers (see the control note below). |

**Control note on `ADMIN`.** By decision of the product owner, `ADMIN` is a full superuser rather than a segregated account-management role. Because a compromised admin credential could therefore produce a validly signed MBSP letter, the following compensating controls are mandatory: named individuals only with no shared accounts; enforced multi-factor authentication; visible tagging on the case and letter whenever an admin performs a review or approval; logged role changes notified to other administrators; and periodic access review. User removal is soft-delete only — hard deletion would destroy the evidence behind signed approvals.

## 5. How an application flows

```text
Applicant prepares package
        |
        v
Email verified  (a passwordless "shadow account" is created if not registered)
        |
        v
Documents uploaded
        |
        v
Automatic completeness check   -- fails --> returned to applicant, no charge
        |
      passes
        |
        v
RM150 paid via MBSPpay
        |
        v
Submission accepted  --> SLA clock starts (3 working days)
        |
        v
Auto-assigned to a review officer by district (derived from mukim/lot)
        |
        v
AI processing: extract, classify, read, evaluate, consolidate, report
        |
        v
Officer reviews findings item by item
        |
   +----+----------------------------+
   |                                 |
correction needed                 complete
   |                                 |
   v                                 v
Applicant corrects            Approval officer approves
(free if MBSP asked;                 |
 RM50 if applicant-initiated)        v
   |                          System digitally signs the letter
   +--> back to review               |
                                     v
                          Applicant downloads; QR verification available
```

## 6. Where the system stands today

| Area | Status |
|---|---|
| Document intake, ZIP safety, hashing | Built and tested |
| Python document worker (text, rendering, page inventory) | Built |
| Provider-neutral AI gateway with schema validation | Built |
| Versioned rule registry with governance metadata | Built; **content not yet confirmed by LJUP officers** |
| Evidence-linked findings and web/PDF reports | Built |
| Accounts, roles, MBSP staff login, Google Sign-In | Built |
| Digital submission workflow (draft, submit, correct, resubmit) | Built |
| Shadow accounts, magic links, companies, district scoping, reference numbers | Built as of 11 August 2026 |
| Payment (MBSPpay) | Not started |
| Auto-assignment and SLA | Not started |
| Approval workflow and digital signing | Not started |
| Full OCR | Prepared but not wired |
| AMIRA Rive animation | Runtime integration complete; **art asset does not exist** |
| Dashboards, full audit hardening, production infrastructure | Not started |

**Honest summary:** the review engine works and the submission workflow works. Everything that makes it an *operational municipal service* — money, assignment, deadlines, approval authority, signed output — has not been built yet.

### Verification status

Twenty-two automated test files exist (17 feature, 5 unit) covering upload validation, archive safety, state transitions, schema validation, aggregation, rule applicability across all seven lighting-type combinations, role access, submission workflow and Phase 2 completion. The Green Camour pilot acceptance fixture expects 12 PDFs and 79 inventoried pages, with 23 units of 150 W and 7 units of 180 W matched between the plan and the photometric report.

The most significant verification gap is **calibration**: only one real officer review case (Billion Site) has been compared against actual manual comments. At least two more real cases are required before the findings can be considered calibrated. This has been the top outstanding item since July 2026.

## 7. Risks

| Risk | Assessment |
|---|---|
| Rule Registry content unconfirmed | The active catalogue is 32 rules, but it is a starter set derived from the initial LJUP document and calibrated against one real case. LJUP officers have not confirmed the requirement text, applicability conditions or severity. **This is the single largest functional gap.** |
| Calibration debt | One real case is not enough to trust accuracy figures. No baseline timing for manual review exists, so no time-saving claim can currently be substantiated. |
| Sending real documents to a cloud AI provider | Requires written departmental approval and a data-handling policy. Not yet obtained. |
| Infrastructure | Current setup is SQLite, `php artisan serve` and leftover Vercel configuration. It cannot carry concurrent users, payment webhooks, persistent queue workers or SLA scheduling. |
| Admin superuser model | Accepted by the product owner with compensating controls (see §4). |
| Internal PKI signature | Not a licensed digital signature under the Digital Signature Act 1997. Adequate for internal verification; would need review if a letter were ever contested. |
| Cost exposure to USD | Most model charges are in USD, so AI cost moves with the exchange rate. |

## 8. Cost

Figures below are **planning estimates, not quotations**, and were prepared on 21 July 2026 in MYR.

### One-off implementation

| Tier | Estimate |
|---|---:|
| MVP, controlled operation | RM650,000 – RM950,000 |
| Recommended production version | RM1,050,000 – RM1,550,000 |
| Enterprise / full integration | RM1,600,000 – RM2,300,000 |

**Recommended budget request: RM1.2m – RM1.5m** for the first production build, excluding AI subscription, annual hosting, payment transaction charges and third-party licences.

### One-off cost by function

| Function | Estimate |
|---|---:|
| MBSP internal login | RM80,000 – RM180,000 |
| Public accounts and submission | RM180,000 – RM380,000 |
| MBSPpay payment integration | RM120,000 – RM280,000 |
| Task assignment and SLA | RM150,000 – RM320,000 |
| AI integration for review | RM220,000 – RM480,000 |
| Reference document management | RM90,000 – RM200,000 |
| Reporting, audit, dashboards | RM120,000 – RM260,000 |
| Security, QA, UAT, deployment | RM160,000 – RM320,000 |
| Project management and contingency (15–20% buffer) | RM130,000 – RM300,000 |
| **Indicative total** | **RM1,250,000 – RM2,720,000** |

### Recurring annual operating cost

| Component | Annual estimate |
|---|---:|
| Maintenance and technical support | RM150,000 – RM350,000 |
| Hosting, storage, backup, monitoring | RM60,000 – RM220,000 |
| AI API usage | RM36,000 – RM300,000+ |
| Periodic security testing | RM25,000 – RM80,000 |
| Training, support, minor enhancements | RM40,000 – RM120,000 |
| **Total** | **RM300,000 – RM900,000+** |

Narrowed recommendation: **RM300,000 – RM600,000 per year**. Monthly AI budget provision of RM3,000 – RM25,000, to be revised against real usage.

### Volume assumptions

| Item | Value |
|---|---:|
| Original applications per year | 150 |
| Re-review probability | 20% – 40% |
| Total reviews per year | 180 – 210 |

### Unresolved: the fee figures do not reconcile

The costing workpaper proposes an **AI API cost-recovery charge of RM80 – RM100 per review**. The approved application fee is **RM150 per submission plus RM50 per applicant-initiated resubmission**.

These were derived independently and have never been reconciled. It is not currently documented whether RM150 is intended to *include* the RM80–100 API recovery or to sit alongside it, nor whether RM150 covers processing cost, cost recovery, or is a statutory fee set on another basis. **This must be settled before the payment phase is built**, because it determines whether the fee schedule is defensible if challenged.

At 180–210 reviews per year, RM150 per submission yields roughly RM27,000 – RM31,500 annually — well below the RM300,000+ annual operating cost. The fee is therefore a partial cost-recovery mechanism, not a self-funding model, and should be presented to management as such.

---

# Part II — Technical description

## 9. Architecture

```text
Browser
   |
   v
Laravel application ......... upload validation, case creation, queue dispatch,
   |                          status endpoint, report rendering, evidence serving,
   |                          authentication, authorisation, audit
   +--> Database
   +--> Private file storage
   +--> Queue
          |
          v
     ProcessReviewCase job
          |
          +--> Python document worker ... PDF metadata, page count, embedded text,
          |                               thumbnails, previews, high-resolution
          |                               renders, region crops, page geometry
          |
          +--> Review engine ............ applicability, evidence retrieval,
                    |                     rule evaluation, consolidation
                    |
                    +--> AI provider gateway --> OpenAiProvider | FakeAiProvider
                                                 (future: LocalAiProvider)
```

**Component responsibilities**

| Component | Owns |
|---|---|
| Laravel application | HTTP, validation, case lifecycle, queue dispatch, progress, report display, evidence access control, database transactions, audit |
| Queue | All long-running work; nothing heavy runs in a web request |
| Python worker | Document mechanics only. It never decides compliance. |
| Review engine | What is checked, when it applies, what evidence is required, which method may be used, which statuses are permitted |
| AI provider gateway | The single application-facing interface to any model. Provider-specific request code must never appear in the rule engine. |
| Knowledge pack | Versioned rules, prompts, schemas, standard comments — `knowledge/ljup/` |
| Private storage | Immutable originals, extracted contents, renders, crops, reports. No predictable public URLs. |

**Provider portability is a hard rule.** Provider-specific file IDs, response IDs and SDK objects must not leak into the business layer. This is what makes a future local LLM a swap rather than a rewrite.

## 10. Document processing pipeline

| # | Stage | What happens |
|---|---|---|
| 1 | Intake validation | Size, extension and detected MIME checked independently; safe internal filename generated; original stored; SHA-256 computed. Originals are never renamed or modified afterwards. |
| 2 | Safe archive extraction | Rejects absolute paths, `../` traversal, symlinks, nested archives beyond depth, excessive extracted bytes, excessive file count. Each archive extracts to a unique case directory. |
| 3 | Document inventory | One record per accepted file. The inventory is itself part of the final report. |
| 4 | PDF text extraction | Embedded text per page, text blocks and coordinates where available, page geometry. Content classified as `TEXT_RICH`, `TEXT_SPARSE`, `IMAGE_ONLY`, `DRAWING_LARGE_FORMAT`, `MIXED_CONTENT`. |
| 5 | Page rendering | Thumbnail for navigation, medium preview for report evidence, high-resolution render for visual review. For A0/A1 drawings, targeted region crops: title block, legend, general notes, electrical notes, pole schedule, feeder pillar details, cable details, photometric summary, signature/revision area. |
| 6 | OCR *(prepared, not wired)* | For scans and for plan title blocks where normal extraction fails. Retains page number, bounding boxes, confidence, engine and language. Low-confidence OCR is passed to the AI with an explicit warning. |
| 7 | Document classification | One primary type plus secondary tags, with confidence and reason. Filename is a hint only, never authoritative. |
| 8 | Application profile extraction | One profile assembled across documents. Every field carries evidence and confidence. Conflicting values are recorded, never overwritten. |
| 9 | Applicability determination | Which rules apply, decided deterministically before any rule is evaluated. |
| 10 | Evidence retrieval | Targeted. The whole package is never sent for every rule. |
| 11 | Rule evaluation | By method: `DETERMINISTIC`, `AI_TEXT`, `AI_VISION`, `CROSS_DOCUMENT`, `MANUAL`. |
| 12 | Cross-document consistency | Compares project title, lot/mukim/district, developer, drawing number and revision, pole type and height, arm type, wattage, quantity, road width, circuit references. Records matches and conflicts. |
| 13 | Consolidation | Deterministic. Direct evidence beats declaration; a checklist tick is not proof; a payment demand is not proof of payment; an unreadable item cannot be compliant; authenticity is always manual. |
| 14 | Report generation | Built from stored structured findings only, never re-read from model memory. Any narrative model may not change statuses or invent evidence. |

### Configured limits (actual values)

| Limit | Value |
|---|---:|
| Total upload | 100 MB |
| Single file | 50 MB |
| Direct files per upload | 100 |
| Files inside an archive | 200 |
| Total extracted bytes | 500 MB |
| Compression ratio | 100:1 |
| Pages per case | 500 |
| AI attempts per task | 3 |

Allowed extensions: `pdf`, `png`, `jpg`, `jpeg`.

## 11. Review engine and rules

**Rules are data, not code.** Each rule carries a permanent `rule_id` (e.g. `LJUP-SPC-C-008`), version, title, source document and page, stage, requirement text, `applies_when` predicates, evaluation method, required evidence types, severity and a standard comment.

**Stages actually present in the rule data:** `SUB` (submission screening) and `PLAN` (plan review). The wider lifecycle stages sketched in the older design notes — pre-construction, post-installation, pre-CCC, handover — exist in neither the rule packs nor the code.

**Rule lifecycle:** `DRAF` → `SEMAKAN` → `DITERBITKAN` → `DITAMATKAN`. Published rules cannot be edited in place; a new version must be issued. Old cases reopen against their original rule snapshot, so historical decisions are never silently reinterpreted.

**Evaluation methods** (`App\Enums\EvaluationMethod`, five values): `DETERMINISTIC`, `AI_TEXT`, `AI_VISION`, `CROSS_DOCUMENT`, `MANUAL`. `RuleRepository::validate()` rejects any rule using a value outside this enum.

**Rule grouping.** Rules carry a free-text `section` (`Submission screening`, `General LJUP review`, `Special LJUP review`, `Cross-document checks`) and a `severity` of `HIGH` or `MEDIUM`. The category vocabulary described in the older planning documents — `UMUM`, `BESI`, `KONKRIT`, `SOLAR`, `BERSYARAT`, `MANUAL_ONLY` — is **not** a field on the rule object. Lighting-type applicability is handled through `applies_when` predicates instead.

### Finding statuses

| Status | Meaning |
|---|---|
| `DITUNAIKAN` | Fulfilled. **Requires at least one evidence record.** Never permitted on an empty evidence list. |
| `BELUM_DITUNAIKAN` | Evidence clearly shows the requirement is missing or not met. |
| `TIDAK_DAPAT_DIKESAN` | The engine searched relevant evidence and could not find the material. Deliberately distinct from "found and non-compliant". |
| `TIDAK_BERKAITAN` | Not applicable, and non-applicability is established — never guessed. |
| `PERLU_SEMAKAN_MANUAL` | Unreadable text, uncertain symbol, insufficient resolution, ambiguity between documents, or uncertain applicability. |
| `PERLU_SEMAKAN_MANUAL_PEGAWAI` | Requires an officer specifically: stamp or signature authenticity, official approval status, payment confirmation, engineering judgement, legal authority. |

**Confidence policy:** 0.90–1.00 clear; 0.75–0.89 minor uncertainty; 0.50–0.74 usually manual; below 0.50 no positive finding permitted. Confidence is not a substitute for evidence.

**Evidence hierarchy**, strongest first: direct visual or text evidence in the plan or report; official supporting document content; structured schedule or table in an attachment; consultant-completed checklist; cover-letter attachment list; filename alone. A checklist tick or cover-letter declaration can never prove the underlying requirement.

**Aggregation is deterministic**, computed in `FindingAggregator`, in this order:

1. Any `BELUM_DITUNAIKAN` → `APPLICATION_INCOMPLETE`
2. Otherwise any manual-review finding → `REVIEW_COMPLETED_PENDING_MANUAL_VERIFICATION`
3. Otherwise any `TIDAK_DAPAT_DIKESAN` → `REVIEW_COMPLETED_WITH_COMMENTS`
4. Otherwise → `NO_MATERIAL_ISSUES_DETECTED_BY_AI`
5. **Never `APPROVED`.**

Note that the implemented ladder does not filter by severity or stage — a single `BELUM_DITUNAIKAN` on any rule marks the application incomplete, and `severity` is currently recorded on rules but never read by the aggregator. The older design notes describe a severity-weighted ladder that was never built. Whether severity should influence the overall result is an open design question.

### Document acceptance policy

An application proceeds if **at least one document is readable and at least one is relevant** to the selected review type. It is not stopped by extra unsupported files, one corrupt file among usable ones, unclear filenames, an `UNKNOWN` classification, or an incomplete checklist. It stops only when there is no safe usable file at all, or every usable document is clearly unrelated.

The stated rationale: *uncertainty is not the same as irrelevance*.

### Current rule pack state

Rule packs **inherit**. `rules.v2.json` declares `"extends": "v1"`, and `RuleRepository::loadVersion()` merges recursively, so the active catalogue is not just the file's own contents.

| Pack | Own rules | Lifecycle | Role |
|---|---:|---|---|
| `ljup v1` | 30 | `DRAF` | Starter catalogue, inherited as the base — not inert |
| `ljup v2` | 4 new + 9 overrides | `DITERBITKAN` | Officer-calibrated pilot; **the active pack** |

**Effective active catalogue: 34 rules, of which 32 are active** — `LJUP-SUB-006` and `LJUP-XDOC-008` are deactivated by override. All are published under the v2 manifest.

The gap is not quantity but **confirmation**: this remains a starter catalogue derived from the initial LJUP rule document and calibrated against a single real case. LJUP officers have not yet reviewed and confirmed the requirement text, applicability conditions or severity assignments. That confirmation is the highest-value outstanding work in the project.

## 12. AI design

The business layer depends on `App\Contracts\AiProvider`, never on a vendor SDK:

```php
interface AiProvider
{
    public function classifyDocument(array $input): array;
    public function extractApplicationProfile(array $input): array;
    public function evaluateRule(array $input): array;
    public function generateReport(array $input): array;
}
```

Implementations: `FakeAiProvider` (deterministic, for local development and tests) and `OpenAiProvider`. A `LocalAiProvider` implements the same interface.

**Task types:** `DOCUMENT_CLASSIFICATION`, `APPLICATION_PROFILE_EXTRACTION`, `FORM_FIELD_EXTRACTION`, `PLAN_LEGEND_EXTRACTION`, `RULE_EVALUATION`, `CROSS_DOCUMENT_COMPARISON`, `REPORT_NARRATIVE`. The explicit anti-pattern is a single prompt asking a model to review everything and produce the conclusion.

**Prompt assembly** is ordered and bounded: system instruction; task type; rule definition; application profile; evidence manifest; the selected files, images and text; the allowed statuses; the output schema; an explicit statement that uploaded documents are untrusted data; an explicit instruction not to infer authenticity or official approval.

**Every response is schema-validated.** The application rejects or retries output that fails schema, uses an unsupported status, claims `DITUNAIKAN` with no evidence, references unknown documents or pages, changes the supplied rule ID, or contains impossible confidence values.

**Every call is audited.** `ai_runs` stores provider, model, provider request ID, task type, rule ID, prompt version, schema version, request hash, input manifest, raw response location, parsed result, status, attempt, latency, input and output tokens, estimated cost, and error details.

**Failure handling:** timeouts and rate limits retry with backoff; invalid schema retries once with a repair instruction then fails the task; safety refusals are stored and routed to manual review; authentication failure stops and is recorded as a configuration error. Retries are capped to prevent runaway cost.

**Cost controls:** deterministic parsing before AI; caching keyed on file hash plus prompt and model version; low detail for ordinary documents and high detail only for dense plan sheets; page-level retrieval rather than whole-package sends; per-case budget that halts non-critical tasks when exceeded.

**Never sent to a model:** entire archives, API keys, and any content not required by the specific task.

## 13. Data model

| Table | Purpose |
|---|---|
| `review_cases` | One submitted package. Processing status, progress, submission status, submission round, applicant, assigned officer and approval officer, timestamps, reference number. |
| `review_versions` | One processing attempt under a given rule/prompt/model set. Enables reprocessing without destroying history. |
| `review_files` | Immutable record per accepted file, with SHA-256, page count, encryption and duplicate flags. Self-referencing for archive members and duplicates. |
| `review_pages` | Per-page extraction and render state, embedded text, OCR text and confidence, thumbnail/preview/high-res paths, page hash. |
| `page_regions` | Title blocks, legends, notes, tiles and evidence crops with normalised bounding boxes. |
| `document_classifications` | Classification per file per version, with confidence, reason and evidence pages. |
| `application_profiles` | The consolidated cross-document profile, with confidence summary and recorded conflicts. |
| `review_rules` | The versioned rule catalogue with governance metadata and rule hash. |
| `review_findings` | One evaluated rule outcome: applicability, status, confidence, narrative, extracted values, manual-review reason, supersession flag. |
| `review_evidence` | Evidence backing a finding: file, page, region, bounding box, quote or value, type, confidence. |
| `review_finding_feedbacks` | Officer corrections of AI findings — the training signal for calibration. |
| `ai_runs` | Complete audit of every provider call. The only place provider-specific identifiers live. |
| `processing_tasks` | Per-job execution tracking supporting retry and partial completion. |
| `generated_reports` | The canonical report artefact with findings hash. |
| `system_config_snapshots` | Non-secret settings that affected a run. No API keys. |
| `review_submission_events` | Audit trail of every workflow transition: actor, from/to status, action, notes, metadata. |
| `review_modules` | Review module registry (`STREET_LIGHTING`, `PLANNING_PERMISSION`, `BUILDING_PLAN`). |
| `reference_documents` | Uploaded reference material per module. |
| `users` | Accounts, with role, account type, MBSP staff identity, Google ID, avatar. |
| `companies` / `company_user` | Company records keyed on SSM number, with multiple representatives per company. |
| `applicant_magic_links` | Expiring single-use access tokens for applicants without passwords. |
| `officer_assignments` | Officer-to-district scoping. |
| `review_reference_sequences` | Sequential official application reference numbers. |
| `notification_deliveries` | Delivery log for notifications — the record of service for applicants contacted only by email. |

Design principles: immutable file records; case separated from each review attempt; ULID identifiers; retries never overwrite history; provider identifiers confined to `ai_runs`.

## 14. API and job flow

**Endpoints**

| Method and path | Purpose |
|---|---|
| `POST /api/reviews` | Create a case from one ZIP or multiple PDF/image files. Returns immediately with case ID and status URL. |
| `GET /api/reviews/{id}` | Status poll: status, progress, current step, counts, warnings. |
| `GET /api/reviews/{id}/report` | Structured report payload. |
| `GET /reviews/{review}/documents/{document}/view` | Document viewer. |
| `GET /reviews/{review}/evidence/{evidence}` | Evidence, access-checked against the owning case. |

**Processing states:** `UPLOADED` → `EXTRACTING` → `INVENTORY` → `EXTRACTING_TEXT` → `RENDERING` → `CLASSIFYING` → `EXTRACTING_PROFILE` → `EVALUATING_RULES` → `CONSOLIDATING` → `GENERATING_REPORT` → `COMPLETED`, or `FAILED`.

**Submission states**, deliberately separate from processing states so applicant-facing status never leaks AI internals. Currently implemented: `DRAF` → `DISERAHKAN` → `DALAM_SEMAKAN` → `PERLU_PEMBETULAN` → `DISERAH_SEMULA` → `SELESAI_SEMAKAN` → `DITUTUP`.

Two further states are required by the locked decisions but **not yet implemented**: `MENUNGGU_BAYARAN` between `DRAF` and `DISERAHKAN`, and `DIBATALKAN` for applications cancelled after three months unpaid. Both arrive with Phase 3.

**Progress** is a weighted stage percentage that never moves backwards: extraction 5%, inventory 10%, text 18%, rendering 30%, classification 45%, profile 55%, rule evaluation 65%, consolidation 92%, report 96%, complete 100%.

**Error codes:** `UPLOAD_VALIDATION_FAILED`, `ARCHIVE_EXTRACTION_FAILED`, `FILE_CORRUPTED`, `PDF_ENCRYPTED`, `PAGE_LIMIT_EXCEEDED`, `DOCUMENT_PROCESSING_FAILED`, `AI_CONFIGURATION_ERROR`, `AI_RATE_LIMITED`, `AI_RESPONSE_INVALID`, `AI_BUDGET_EXCEEDED`, `REPORT_GENERATION_FAILED`, `CASE_FAILED`.

## 15. Security, privacy and audit

**Primary risk:** uploaded documents contain names, addresses, phone numbers, signatures, professional registration details, property information and infrastructure detail. Sending them to an online model provider requires written departmental approval, which has not yet been obtained. Until then, only approved or anonymised test material may be used.

A stated constraint worth repeating: *do not claim that data remains local while using an online provider.*

**Controls in place**

- Originals immutable and SHA-256 hashed.
- Archives validated before extraction; path traversal, decompression bombs, nested archives, symlinks and excessive counts blocked.
- Document text treated as untrusted data. Prompt-injection defences: strong system prompt, narrow task prompts, strict schemas, backend status and evidence validation, no tool access for the review model, evidence manifest bounding valid document and page references, deterministic aggregation.
- API keys server-side only, never sent to the browser, never logged.
- Evidence routes verify the evidence belongs to the requested case.
- Logs must not contain document text, signatures, API keys, personal contact details or raw provider request bodies — IDs and hashes only.

**Audit capture:** uploader identity, timestamps, original filename, size and hash, extraction results and rejected entries, state transitions, classifications, rule/prompt/model versions, provider request ID, which pages were sent to the provider, response locations, validation outcome, retries, errors, report hash, and deletion actions.

**Planned hardening** (not yet built): append-only hash-chained audit log, encryption at rest, malware scanning, rate limiting, security headers, privacy impact assessment and penetration testing before production.

### Retention

| Class | Examples | Retention |
|---|---|---|
| Official record | Originals, findings, approval decisions, signed letters, audit trail, payment records | Permanent, per MBSP's *Jadual Pelupusan Rekod* |
| AI decision basis | Parsed responses, model and prompt versions, run metadata | Permanent — small, and it justifies a decision |
| Bulky derivatives | Page renders, thumbnails, previews, region crops, OCR text | Purge ~90 days after case closure; regenerate on demand from the immutable original |
| Raw AI payloads | Unparsed provider responses | 12–24 months |
| Transient | Magic link tokens, sessions, abandoned uploads | Days |

Retention windows are configurable rather than hardcoded, pending confirmation against MBSP's approved records disposal schedule under Akta Arkib Negara 2003. Most storage volume is derivative and regenerable, which is why tiering matters: the originals are the record, the renders are not.

---

# Part III — Forward development

## 16. Decisions locked on 11 August 2026

These constrain everything that follows. Changing any of them requires re-planning the affected phase.

| # | Decision |
|---|---|
| 1 | Submission without registration creates a **passwordless shadow account** keyed to a verified email. One code path, full audit trail, claimable later. |
| 2 | Applicant access without a password is via **expiring single-use magic links**. |
| 3 | Roles are `PUBLIC_APPLICANT`, `MBSP_CLERK`, `MBSP_OFFICER`, `MBSP_APPROVAL_OFFICER`, `ADMIN`. |
| 4 | `MBSP_MANAGER` is renamed to `MBSP_APPROVAL_OFFICER` — a data migration, not a new role. |
| 5 | `ADMIN` is a full superuser, with the compensating controls in Part I §4. |
| 6 | User deletion is soft-delete only. |
| 7 | Companies are registered by **SSM number only**, unverified, deduplicated on that number, shown to officers as applicant-declared. |
| 8 | **RM150 is due before a submission is accepted.** The SLA clock starts at payment webhook confirmation. |
| 9 | Deterministic completeness checks run **before** the payment page. |
| 10 | The **RM50 resubmission fee is waived when MBSP requested the correction**; charged only for applicant-initiated resubmission or scope change. |
| 11 | Unpaid applications are **cancelled after 3 months** and the bill terminated. |
| 12 | Refunds are approved manually by senior management; the system only records them. |
| 13 | District is **derived from a mukim/lot lookup**, not chosen freely by the applicant. |
| 14 | SLA is **3 working days**; submissions after **4:00 pm** count from the next working day. |
| 15 | Digital signature is **internal PKI plus a public QR verification page**, behind a replaceable `DocumentSigner` contract. Not a licensed digital signature, and letters must not claim otherwise. |
| 16 | Retention is **tiered** as set out in §15. |
| 17 | Production hosting is **on-premise at the MBSP data centre**. |
| 18 | Local LLM is out of scope for now; the `AiProvider` contract keeps it a swap. |

## 17. Roadmap

| Phase | Release | Version | Status |
|---|---|---|---|
| 1 | Baseline Beta and Rule Registry | `v0.6.0-beta.1` | Technically complete; awaiting LJUP officer confirmation of rule content |
| 2 | Digital Submission | `v0.7.0-beta.1` | Technically complete 11 August 2026 |
| **2B** | **Infrastructure Migration** | `v0.7.5-beta.1` | **New — prerequisite to Phase 3** |
| 3 | Payment and Officer Operations | `v0.8.0-beta.1` | Not started |
| 4 | Hybrid AI, OCR and AMIRA | `v0.9.0-beta.1` | Not started |
| 5 | Letters, Dashboards and Production Hardening | `v0.10.0-rc.1` | Not started |
| 6 | Production | `v1.0.0` | Not started |

### Phase 2B — Infrastructure migration *(new)*

Inserted because payment webhooks, persistent queue workers and SLA scheduling cannot run reliably on the current setup. Doing this after Phase 3 would mean building payment against infrastructure that cannot support it.

- PostgreSQL or MySQL replacing SQLite
- Redis for queue and cache
- Supervised persistent queue worker
- Cron scheduler for SLA sweeps, cancellation sweeps and retention purges
- S3-compatible object storage (MinIO on-premise)
- Removal of `vercel.json` and `.vercelignore`
- Staging environment matching production
- Backup and restore actually tested, not merely configured

### Phase 3 — Payment and officer operations

Payment: MBSPpay integration; RM150 gate before acceptance; conditional RM50 resubmission fee driven by recorded correction cause; unique payment reference derived from the application reference number; verified idempotent webhooks; receipts; reconciliation; three-month cancellation sweep with warnings at ~60 and ~80 days and bill termination; manual refund recording.

Two external confirmations are required early: whether MBSPpay can **void** a bill via API, and what happens if a cancelled bill is nevertheless paid.

Officer operations: district-based auto-assignment with workload balancing and leave awareness; manual reassignment with mandatory reason; officer work queue; optimistic locking so two officers cannot conflict on the same finding.

SLA: an admin-editable working calendar of weekends and Penang public holidays — never hardcoded; deterministic due-date calculation; the clock **pauses** while the case sits in `PERLU_PEMBETULAN` so officers are not penalised for applicant delay; warnings before expiry; escalation to the approval officer on breach.

District: check first whether MBSP GIS can serve the mukim/lot to district mapping before building and maintaining an in-house cadastral table. Fallback to clerk assignment, flagged.

### Phase 4 — Hybrid AI, OCR and AMIRA

OCR: an `OcrProvider` contract mirroring `AiProvider`; full OCR for scanned and text-free pages; high-DPI tiling for engineering drawings, since naive full-page OCR will not read title blocks and annotations; page and region level confidence with low-confidence pages routed to manual review; at least two engines compared before selection.

Hybrid AI: `LocalAiProvider` behind the same contract; an `AiTaskRouter` splitting cheap work (simple classification, light extraction, status summaries, navigation help, format checks) from expensive work (complex reasoning, cross-document contradictions, technical rule evaluation, final report generation, low-confidence cases); confidence-based escalation; per-case token and cost caps; response caching on `ai_runs.request_hash`; an AI cost dashboard.

Local model selection is scored on rule and evidence accuracy (30%), technical drawing vision (20%), structured output reliability (15%), Malay and English comprehension (10%), hallucination and unsupported positives (10%), latency (5%), hardware cost (5%), licence and support (5%). **Unsupported positive findings are a critical failure regardless of overall accuracy.** Migration proceeds through provider abstraction, local proof of concept, offline evaluation against a gold dataset, dual-run shadow comparison, controlled switchover, then local-only.

AMIRA: the Rive runtime integration is already complete — vendored runtime `@rive-app/canvas` 2.39.2, artboard and state machine both named `AMIRA`, 13 state triggers plus `isSpeaking`, `pointerX`, `pointerY` and `progress`, with a three-tier fallback. **The `.riv` art asset does not exist**; the current file is a 37-byte placeholder. The full input contract is in `22_AMIRA_RIVE_SPEC.md`, and a validation harness is available at `/dev/amira-states` in non-production environments. A layered vector SVG with a suitable rig decomposition already exists at `public/assets/brand/amira-vector-layers.svg` and is currently unreferenced by any code — it is a plausible starting point for the artwork.

Also in this phase: AMIRA Frontdesk conversational guidance, Voice-to-Text, and an Officer Review Copilot for reading evidence and findings. The constraint holds throughout: AI does not make official decisions.

### Phase 5 — Letters, dashboards and production hardening

The Surat Pengesahan Semakan: generation conditions, mandatory approval officer sign-off for every letter, an official definition of `SEMAKAN_BERJAYA`, letter template and reference number, `DocumentSigner` and `InternalPkiSigner`, QR verification page with certificate rotation so old signatures remain verifiable, letter lifecycle `DRAF` → `MENUNGGU_PENGESAHAN` → `DIKELUARKAN` → `DIBATALKAN` → `DIGANTI`.

Dashboards for applicant, clerk, officer, approval officer and administrator, with operational metrics: application volume and status, SLA compliance, backlog, correction rate, AI performance and cost, payment status.

Hardening: hash-chained audit trail, admin action auditing, tiered retention purge jobs, monitoring, alerting on failed jobs and stuck queues, backup and disaster recovery, security testing, UAT with real users, release candidate.

### Phase 6 — Production

All critical acceptance criteria passed; no open critical or high security issues; approved data, retention and backup policies; SOPs for officers, administrators and incident escalation; user training; support and rollback plans; verification of every external integration; product owner go-live approval; tag `v1.0.0`.

## 18. Beyond v1.0.0

These are directional, not committed.

**Additional review modules.** `PLANNING_PERMISSION` (Kebenaran Merancang) and `BUILDING_PLAN` (Pelan Bangunan) already exist as registry entries. A module cannot be activated without its own reference documents, rule pack, prompts, score weightings, test cases and officer confirmation — the framework is reusable, the domain knowledge is not. Fees, SLA and roles should already be configured per module rather than hardcoded to LJUP, so adding a module is configuration plus knowledge, not re-engineering.

**Spatial and CAD review.** Checks requiring real geometry — distances, offsets, route intersections — need DWG/DXF or GIS input and are out of scope today. The older design notes reserve a `SPATIAL_FUTURE` evaluation method for this, but it is not in the `EvaluationMethod` enum and would have to be added.

**Local LLM in production.** Phase 4 builds the routing; running fully local depends on GPU budget and benchmark results. On-premise hosting was chosen partly to keep this open.

**OSC integration.** Explicitly out of scope, but repeatedly flagged as a plausible later requirement. Keeping the reference number scheme and status vocabulary clean makes that easier.

**Applicant self-service improvements.** Pre-submission validation feedback, saved templates, and reuse of a previous application as a new draft.

## 19. Open decisions

### Resolved on 11 August 2026

Public account verification method; timing of the RM150 payment; refund policy; resubmission fee policy; unpaid application policy; role and approval authority matrix; company verification method; district determination method; SLA and cut-off; digital signature method; retention approach; production infrastructure.

### Still outstanding

| Decision | Owner | Blocks |
|---|---|---|
| **Rule Registry content confirmation** | LJUP officers | Everything — 32 active rules, none officer-confirmed |
| Reconciliation of RM150 against the RM80–100 API cost recovery | Product owner + finance | Phase 3 |
| Whether MBSPpay can void bills via API | MBSPpay team | Phase 3 |
| Whether MBSP GIS can serve mukim/lot to district mapping | GIS team | Phase 3 |
| Actual retention periods per the records disposal schedule | Unit Pengurusan Rekod | Phase 5 |
| Policy on using real documents with cloud AI | Product owner + legal | Pilot with real data |
| Official definition of `SEMAKAN_BERJAYA` | LJUP officers | Phase 5 |
| Letter name, wording, format and official effect | Product owner | Phase 5 |
| Whether clerks may view plan documents or only payment metadata | Product owner | Phase 3 |
| Confidence threshold for local/cloud escalation | Technical | Phase 4 |
| GPU budget for local LLM | Management | Phase 4 scope |
| PDPA position for a local authority | Legal | Phase 6 |
| MBSP SSO policy and method | IT | Phase 6 |
| Official notification channels | Product owner | Phase 3 |
| Baseline version number for the prototype | Product owner | Release management |
| Technical items that must remain manual | LJUP officers | Rule Registry |

### Outstanding requests to management

Carried forward from the management brief and not recorded as granted:

1. Authorise continuation of the controlled pilot.
2. Appoint technical officers as a reference group and rule validators.
3. Permit use of de-identified past cases for testing.
4. Set performance indicators and the pilot evaluation period.
5. Authorise a study of security, privacy, infrastructure and operating cost.
6. Determine the next module priority after street lighting.

---

# Appendices

## Appendix A — Glossary

| Term | Meaning |
|---|---|
| AIRS | AI Review Studio — the platform |
| AMIRA | AI Multidocument Interpretation and Review Assistant — the assistant persona |
| LJUP | Pelan Lampu Jalan Untuk Pembangunan — street lighting plan for development |
| LJUP Umum / Khusus | General and street-lighting-specific requirement sets |
| MBSP | Majlis Bandaraya Seberang Perai |
| MBSPpay | MBSP's payment gateway |
| SSM | Suruhanjaya Syarikat Malaysia — company registration |
| Mukim / Daerah | Subdistrict / district |
| Rule pack | A versioned catalogue of review requirements |
| Review version | One processing attempt under a given rule, prompt and model set |
| Shadow account | A passwordless user record created for an applicant who did not register |
| Green Camour | The pilot acceptance test package — 12 PDFs, 79 pages |
| Billion Site | The single real officer review case used for calibration to date |

## Appendix B — Known divergences in older documents

Recorded rather than silently reconciled, so that anyone reading the older technical documents knows what to distrust.

1. **Job naming differs** between `02_SYSTEM_ARCHITECTURE.md` and `09_API_AND_JOB_FLOW.md` for the same pipeline steps, and the two disagree on whether rule evaluation is one batched job or two parallel jobs.
2. **OCR has no job** in either job list, despite being a numbered pipeline stage.
3. **`report_url` is inconsistent** within doc 09 between the create and status responses.
4. **The `AiProvider` contract exposes 4 methods but doc 04 defines 7 task types** — `FORM_FIELD_EXTRACTION`, `PLAN_LEGEND_EXTRACTION` and `CROSS_DOCUMENT_COMPARISON` have no interface method.
5. **Severity is recorded but unused.** Rules carry `severity` of `HIGH` or `MEDIUM`, but there is no PHP severity enum, no `is_critical` flag, and `FindingAggregator` never reads the field. The severity-weighted aggregation described in doc 06 was never implemented; see §11 above for what the code actually does.
6. **The confidence threshold has no stated enforcement point.** Doc 06 §8 proposes `>= 0.80` for `DITUNAIKAN`, but the validation list does not check it.
7. **No status exists for a failed AI task.** Whether a failure surfaces as `PERLU_SEMAKAN_MANUAL`, `TIDAK_DAPAT_DIKESAN` or no finding at all is unspecified.
8. **Provider file deletion granularity conflicts** — per job in doc 04, per case in doc 12.
9. **Redaction is disabled by default** despite personal data being named as the primary risk, and no component owns redaction.
10. **Two retired phase schemes.** Doc 31 §23 (Phases A–D) and the management brief Slide 16 (Phases 1–4) describe an older roadmap superseded by the versioned phases in §17 above. Doc 33 also has its own four *cost* phases which do not map one-to-one onto delivery phases.
11. **Rule category taxonomy — resolved.** Doc 34 §5.3 and its Release A section list two different category vocabularies. Checked against the registry: **neither exists.** Rules have no `category` field at all; they carry a free-text `section` and a `severity`. Lighting-type applicability runs through `applies_when` predicates. Both lists in doc 34 should be corrected.
12. **Docs 31, 32 and the management brief understate current capability.** They describe a system with no accounts, roles, payment or correction flow. Accounts, roles, submission workflow, drafts, resubmission and document versioning all now exist.

## Appendix C — Development history

Milestones with confirmed dates:

| Date | Event |
|---|---|
| 20 July 2026 | Prototype declared ready for controlled user review; background and progress documents issued |
| 21 July 2026 | Cost estimate prepared |
| 27 July 2026 | `v0.5.0-beta.1` baseline tagged; `v0.6.0-beta.1` Phase 1 released with lighting-type intake and Rule Registry; phase roadmap issued |
| 4 August 2026 | `v0.7.0-beta.1` Phase 2 started — digital submission workflow, MBSP staff login, Google Sign-In, role-aware dashboards |
| 11 August 2026 | Phase 2 completed — shadow accounts, magic links, companies, officer district scoping, reference numbers, notification delivery log. Upgrade decisions locked. AMIRA Rive specification issued. |

The 36-step build chronology from `32_RINGKASAN_PERKEMBANGAN_AIRS.md` covers, in order: Laravel foundation and data model; upload and archive security; the Python document worker; the AI gateway and LJUP rules; the review engine and reports; testing and the Green Camour acceptance case; documentation and hardening; creative direction and readiness scoring; reference-based testing; Bahasa Melayu reports; profile extraction from LJUP Umum; officer report ordering; PDF alignment with the web report; condensed commentary; minimising manual-review status; the conversational New Review flow with AMIRA; `test_subject` testing; design pack implementation; single conversation bubble; AMIRA matched to the reference image; officer commentary comparison against Billion Site; official AIRS and MBSP identity assets; management presentation material; the summary landing page; AMIRA poses by status; two-stage introduction; problem and support illustrations; the management governance section; hero bubble positioning; desktop responsiveness; process step icons; AMIRA profile and idle motion; removal of language as a marketing feature; the settings menu for modules and reference documents; the incomplete-document pose; and the minimum-one-usable-document acceptance policy.

## Appendix D — Key figures

| Figure | Value |
|---|---|
| Green Camour acceptance | 12 PDFs, 79 pages, 23 units @ 150 W, 7 units @ 180 W |
| Billion Site calibration | 14 rules: 10 `DITUNAIKAN`, 4 `TIDAK_BERKAITAN` |
| Active rule catalogue | `ljup v2` inheriting v1 — 34 rules, 32 active |
| Own contents | v1: 30 rules (`DRAF`, inherited); v2: 4 new + 9 overrides (`DITERBITKAN`) |
| Test files | 22 (17 feature, 5 unit) |
| Annual application volume assumption | 150 original, 180–210 total reviews |
| Application fee | RM150 submission, RM50 applicant-initiated resubmission |
| Proposed AI cost recovery | RM80–RM100 per review |
| One-off build estimate | RM1.2m – RM1.5m recommended |
| Annual operating estimate | RM300k – RM600k recommended |
| SLA | 3 working days, 4:00 pm cut-off |
| Upload limits | 100 MB total, 50 MB per file, 500 pages per case |
