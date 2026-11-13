# iNDECS and AIRS integration contract

> **Superseded in part by [ADR-383](../adr/ADR-383-airs-merged-into-indecs.md) (16 November 2026):** AIRS is now an in-process iNDECS module, not a separate service. Where this contract describes a separate AIRS service, its own API, accepted submission before the first check, or the journey order, ADR-383 takes precedence. Ownership of evidence, findings, advisory scoring and officer authority below still applies.

> **AI ecosystem alignment — 9 November 2026:** [Current roles and integration boundaries](ai-ecosystem.md). AMIRA remains application assistance; MATRIX is the planned document specialist; MINDA is internal intelligence. Existing authority, free-precheck and payment gates remain.


Decision date: 2026-11-04 (Asia/Kuala_Lumpur). Status: Accepted product direction; implementation pending.

This contract governs the combined application journey. It supersedes earlier standalone AIRS submission, fee, scoring and workflow plans where they conflict. Acceptance of this direction does not certify that the integration, payment gateway, OCR or AI services are implemented or production-ready.

## 1. Responsibilities

| Capability | Authoritative owner |
|---|---|
| Portal, identity, applicant/PSP accounts, company membership and permissions | iNDECS core; AIRS trusts iNDECS identity |
| Draft, application form, official reference and formal submission | iNDECS core with departmental configuration |
| Submitted originals, document-set versions, requirements and correction rounds | iNDECS; originals and history remain immutable |
| Fees, payment confirmation, waivers, refunds, assignment, SLA and notifications | iNDECS core |
| Official application status, decisions, issued letters and audit/history | iNDECS and authorised officers |
| Document processing, classification, extraction and technical rule evaluation | AIRS |
| Review cases/versions, rule packs, evidence, findings, review reports and AI-run audit | AIRS; iNDECS retains linked results and confirmed checklists |
| Applicant and officer assistance | AMIRA within the authorised iNDECS journey |
| Spatial submission and CAD-to-GIS conversion | E-Plan Submission; iNDECS stores its references/status |

AIRS appears as a review module within the iNDECS experience, with one public application journey. This does not require a codebase merger. AIRS is not a second official intake portal. Existing AIRS identity, billing, submission and letter features are reusable implementation material for iNDECS core, not competing authorities. AIRS can continue processing review cases it already holds during an iNDECS outage; this does not authorise independent formal public submissions or duplicate decisions.

## 2. Applicant journey and payment gate

1. Applicant authenticates through iNDECS and creates a draft.
2. Applicant uploads against the department's versioned document checklist and runs the free preparation precheck.
3. Applicant resolves missing/unusable documents and confirms suggested particulars.
4. iNDECS assesses the applicable submission fee. **Payment must be confirmed before formal submission can be accepted.** An exception requires an explicitly authorised, audited waiver under an approved policy; applicants cannot waive payment themselves.
5. iNDECS accepts the applicant's formal submission against the paid/waived document-set version and writes submission history. Payment alone is not submission, approval or a completed review.
6. Only after accepted submission does iNDECS dispatch the formal AIRS technical review.
7. Officers assess findings and evidence, request corrections or make official decisions in iNDECS. Feedback returns to AIRS without overwriting the original AI findings.

Fee amounts will be decided later. Earlier RM150/RM50 figures are legacy staging values, not approved integrated tariffs. Whether a correction/resubmission round incurs another charge, and the applicable waiver/refund/expiry rules, remain policy decisions. Every chargeable submission must clear its applicable fee before acceptance; do not infer that correction rounds are either free or chargeable. Department-specific SLA durations and start/pause rules must be configured in iNDECS; do not inherit LJUP's three-day/four-o'clock rules as family defaults.

Payment and submission operations must tolerate retries. Use authenticated, idempotent payment confirmation and submission transitions; never accept a browser redirect or an uploaded payment document as payment proof. Bind the fee assessment and accepted submission to a document-set version, and reassess relevant changes before accepting a changed package. Payment callbacks must not create duplicate submissions or review jobs. If AIRS is unavailable, retain the accepted submission and queue its review without asking for payment again.

## 3. Free applicant precheck

Purpose: help assemble and identify documents, not assess technical compliance. No paid generative AI or cloud AI classification, vision, conversation or rule-evaluation calls are permitted in this stage. OCR in this stage is local; a paid cloud OCR service is not an implicit fallback.

| Check | Boundary |
|---|---|
| Required documents | Department-configured checklist and applicability; applicant uploads/maps files to named slots |
| File usability | Safe archive handling, allowed format/MIME, size, password protection, corruption, duplicates and page inventory |
| Text retrieval | Embedded text first; bounded local OCR for sparse/image-only pages where available |
| Suggested identification | Fixed text/rule matching may suggest document type, title, lot number or reference, with document/page provenance and applicant confirmation |
| Basic mismatch | Fixed comparisons against applicant-entered particulars; flag for confirmation, do not decide validity |
| Uncertain identification | Applicant maps the file or selects relevant pages; no automatic paid AI escalation |
| Excluded | Technical drawing interpretation, engineering calculations, semantic compliance review, substantive cross-document technical comparison, authenticity checks and readiness/compliance scoring |

OCR reads content to retrieve text; it does not establish technical correctness, legal validity, signature authenticity or compliance. A checklist attachment does not prove that the attached document satisfies a requirement. At least one readable/relevant file is not enough to establish formal completeness. Unsafe or unusable files require replacement; uncertain classification can be confirmed manually. Where OCR is unavailable, preserve manual checklist completion rather than silently calling cloud AI.

Use preparation labels such as **Dilampirkan**, **Belum dilampirkan**, **Tidak dapat dibaca** and **Perlu pengesahan pemohon**. Explain: "Semakan ini membantu menyediakan dokumen sebelum penyerahan. Kandungan teknikal dan pematuhan akan disemak dalam proses rasmi." Do not show technical findings or an approval/rejection label in the free precheck.

## 4. Resource and abuse controls

- Require a verified identity and owned/authorised draft before resource-consuming precheck; no anonymous processing endpoint.
- Configure file/page/size, OCR-page and processing-time budgets per application type, plus per-account and per-draft rate limits. Exact quotas remain to be set from measured workloads.
- Permit one active precheck per draft; deduplicate repeated requests and retries.
- Cache/reuse unchanged-file results using content hashes, processing version and relevant checklist/configuration version. Enforce authorisation on every reuse; hashes must not disclose another applicant's documents.
- Reprocess only changed documents or invalidated checks. Repeated clicks do not rerun OCR on unchanged files.
- Inspect bounded relevant pages first; use manual mapping/page selection when the budget is exhausted.
- Separate the precheck queue and its resource budget from formal/officer work so public traffic cannot starve accepted reviews.
- Log usage, limits and failures; use bounded retry and backoff. Never auto-escalate free checks to paid AI.

## 5. Formal review and officer authority

The paid/waived, accepted application version may invoke AIRS extraction, classification, cross-document analysis and configured technical rule packs. Payment does not make unsupported modules available: each module requires its own validated rule pack, references, prompts, test cases and officer calibration. LJUP is the documented active pilot; planning/building slots alone do not establish review coverage.

Every material finding retains document/page evidence, confidence, rule/prompt/processing versions and an immutable review identity. Present evidence, AI explanation and officer action separately. Keep the original AI result and append officer feedback; do not overwrite history.

Any readiness score is advisory **Skor Kesediaan Dokumen**, not approval/rejection or a probability of compliance. Keep critical unresolved items and manual checks visible regardless of the average score. No score may automatically reject, change application state, hide findings or prevent authorised officer feedback. Replace legacy "Permohonan Ditolak"/"Lulus Semakan" score semantics. The safeguard applies to every review, independently of billing flags. Official approval or rejection requires an authorised, audited officer action in iNDECS.

## 6. AMIRA

**Backend ownership update (2026-11-04, ADR-353):** AMIRA is an independent backend at `C:\Users\Asyra\Documents\AMIRA`, outside AIRS and iNDECS. Its first implemented capability is a stateless, authenticated prepared-guidance/catalogue service extracted from AIRS. AIRS consumes a validated local published copy; iNDECS keeps its local guidance. No live conversation, model inference or record retrieval is enabled by this extraction. UI/assets remain in their host applications. The AIRS review AiProvider stays in AIRS. Future assistant conversation and scoped retrieval belong to AMIRA, using iNDECS identity and authorised source APIs, rather than an AIRS-owned assistant endpoint.

AMIRA is the shared supporting assistant, not a separate application, account system or decision maker. Applicant precheck guidance uses prepared/local explanations and does not spend paid generative AI credits. Future officer assistance can explain authorised AIRS findings and evidence; broader summaries/search use authorised iNDECS services. Both require explicit implementation and grounding. Do not expose other applicants' data, fabricate confidence, auto-submit forms, trigger payments or change state. Current iNDECS AMIRA remains explicitly local page guidance until a real authenticated service is connected.

## 7. Integration and storage boundary

Use service-authenticated versioned APIs and signed, idempotent events, not direct cross-system table reads/writes. iNDECS remains the identity provider; role mapping must preserve department/record scopes and must not copy legacy privilege escalation at login.

The target review contract is `/api/v1/reviews`, status/completion callbacks, findings retrieval and feedback write-back. Each request/result identifies the iNDECS application, exact document-set version, AIRS review/run version and applicable rule version; use signed, scoped document access and correlation/idempotency identifiers. Discard or retain as historical any late result for a superseded document set; never replace the current checklist silently. Exact schemas and paths for precheck are implementation work, not existing endpoints.

The proposed `/api/v1/documents/classify-and-extract` must not become a paid pre-submission bypass. Free precheck is restricted to section 3; paid AI extraction/classification belongs to an accepted formal review. Document expiry/revocation and evidence access must retain record permissions.

Older programme documents specify separate AIRS/iNDECS databases; current local READMEs describe co-location in database `iNDECS`, schema `airs`. This decision does not migrate or approve a new physical database topology. Record and resolve that deployment discrepancy separately. Logical ownership and API-only access apply regardless of physical hosting.

## 8. Delivery and acceptance

Documentation alignment is not a runtime change. Existing AIRS standalone/staging behaviour, including dummy billing, low-score labels and letters, is not certified as compliant by this contract. Existing iNDECS staff registration/document-verification slices remain available; the new applicant payment gate applies when the public integrated submission journey is implemented, not retroactively to legacy records.

- [ ] Implement free checklist precheck and bounded local text/OCR processing with manual fallback.
- [ ] Verify no free-precheck or AMIRA path invokes paid AI/cloud processing.
- [ ] Implement resource limits, authorised cache reuse, deduplication and queue separation.
- [ ] Implement iNDECS fee assessment, verified payment gate and version-bound formal submission; prove unpaid submissions and duplicate callbacks cannot dispatch reviews.
- [ ] Implement iNDECS identity federation and scoped service APIs/callbacks; remove competing public intake in integrated mode.
- [ ] Move/reuse legacy AIRS operational features in iNDECS core and reconcile existing accounts/cases/references/history without duplicates. Inventory records and define mapping/cut-over/rollback before any data migration.
- [ ] Apply advisory-only scoring consistently and preserve evidence and officer feedback at every score.
- [ ] Verify immutable document rounds, late/out-of-order callbacks, permission checks and outage recovery.
- [ ] Validate each activated rule pack with the responsible department and calibrate against officer-reviewed cases.
- [ ] Set fees, correction-round charging, authorised exemptions, refund/expiry policy, SLA rules and numeric resource quotas before go-live.
- [ ] Resolve physical database topology in a separate deployment decision; preserve API-only ownership.

## 9. Implemented connector increment (ADR-354, 2026-11-04)

The version-one service connector and officer review workspace are implemented. AIRS exposes authenticated capabilities, multipart review intake, status/findings and attributed/idempotent feedback. iNDECS persists requests/results/feedback in `integration`, uses its own permission-checked document originals for evidence, and deduplicates transport retries. Legacy AIRS public/session routes refuse service-owned cases. Initial document transfer uses multipart bytes/checksum manifests instead of signed download URLs; polling and scheduled recovery are implemented instead of push callbacks. Browser OIDC remains a separate identity milestone; a dedicated service credential does not grant browser/user authority.

Dispatch requires an immutable `core.accepted_submissions` record, including independently confirmed payment/approved waiver and the exact document manifest/hash. This increment provides no writer endpoint/form/command for that ledger, no fee tariff, payment-gateway bypass or automatic acceptance of staff-registered cases. The payment/submission workflow must produce that proof before live dispatch. Unsupported modules remain blocked, and changed documents require a newly accepted version. No acceptance or payment records are populated in operational data by tests. Department rule packs and public paid intake remain launch gates, not completed features.

Use `php artisan airs:check` in iNDECS to verify the authenticated connection without invoking AI. Keep the iNDECS `airs` queue/scheduler and AIRS worker/scheduler supervised; `airs:sync` polls active work and pending feedback, while `indecs:recover-reviews` recovers interrupted initial AIRS queue delivery. AMIRA is unchanged and remains independent under ADR-353.
