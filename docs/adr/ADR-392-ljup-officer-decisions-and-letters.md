# ADR-392: LJUP officer recommendations, decisions and letters

Date: 2026-11-17.

Status: Increment 1 accepted for implementation by owner on 2026-11-17: internal recommendations and draft letter previews only. Official outcomes, delegation and issued letter templates below remain recommendations, not confirmed JKEJ policy. No official transition or issuance is authorised by this increment.

## Context

ADR-383 delivers the applicant's paid two-check journey and JKEJ's review workspace. A handed-over application has a final completed AIRS check and accepted-submission evidence. Officers can record attributed finding comments and field corrections, but there is no core decision/letter subsystem yet. `WorkflowService` exists for configured transitions; its application-access helper currently covers PKM and Bangunan, not LJUP.

The retired Plan Review application is reference material only. Its trial letter, role rules and direct state mutation are not an approved iNDECS letter process. Its PDF template explicitly says it is a test copy. Do not copy its authority model, mandatory AI-feedback coverage, typography or auto-closing behavior into iNDECS.

## Recommended process

1. The reviewing officer records an internal recommendation, reasons, proposed conditions, relevant document references and any explanation for missing documents. The recommendation is a human record, separate from AIRS findings and score.
2. A different delegated officer reviews the recommendation and either returns it internally or confirms an official decision. A senior role alone does not establish statutory delegation. System administration does not automatically grant signing authority.
3. An officer prepares a letter from a versioned department-approved template. The decision, wording, recipient and conditions are reviewed before issuance. Issuance is a separate explicit action with recent staff authentication and the configured delegation checks.
4. The applicant sees only an issued letter and the applicant-safe decision summary. Internal recommendations, finding comments, AI rationale, draft letters and approval discussions remain internal.

### Proposed outcomes

| Outcome | Meaning | Letter |
|---|---|---|
| Lulus | Human approval with explicit conditions where needed | Surat kelulusan pelan lampu jalan |
| Perlu pindaan | Request specified changes/documents; keep the case open | Surat permintaan pindaan / dokumen tambahan |
| Tolak | Reasoned human refusal by the delegated authority | Surat keputusan penolakan |

Keep conditional approval as conditions attached to Lulus rather than inventing another independent outcome. Do not introduce approval duration, appeal periods, amendment deadlines, tariffs, legal clauses or delegated limits without confirmation. Rejection must never be produced by an AIRS score or missing-document flag.

An officer's Perlu pindaan decision is different from the applicant's pre-handover paid amendment. Define the post-handover resubmission and reassessment contract before enabling that official outcome: retain previous accepted document sets, decide how new documents are accepted and reviewed, and preserve the two-check payment cap. It must not silently enable check 3, reuse the closed applicant amendment window or charge another fee.

### Proposed responsibility boundaries

| Responsibility | Recommended holder | Boundary |
|---|---|---|
| Prepare recommendation and letter draft | Existing engineering reviewer | Existing scoped review permission; no official state change |
| Confirm decision | Separately delegated JKEJ approver | New explicit scoped permission; different actor from recommender |
| Issue/sign letter | Delegated signatory | Explicit issuance permission and current delegation; recent staff authentication |
| Configure system | System administrator | No automatic approval/signing authority |

The decision approver and signatory may be the same delegated person if JKEJ confirms it. Do not assign new approval/issuance permissions to existing roles by assumption. Preserve department, application-type and district scope at route and service boundaries.

## Technical direction

Build reusable recommendation, decision and letter records in `core`, with LJUP configuration and the existing `/kejuruteraan` workspace as the first consumer. Keep department-specific fields in extension records, not on the application core table. AIRS remains read-only evidence for this process and acquires no official decision methods.

Recommendations and draft letters have append-only revisions. Final decisions and issued letter content are immutable. Corrections create attributed superseding records; no in-place replacement or silent deletion of an issued record. Pin the final review version, accepted-submission/document manifest and recommendation revision used for each decision. Retain superseded evidence.

The core service checks authorization again, locks the application, verifies expected row/recommendation versions, and commits the human decision, configured workflow transition, stage history and audit together. Extend the shared workflow access contract deliberately for LJUP and keep PKM/Bangunan tests green. Do not add a controller-only parallel status updater.

An issued letter pins a template version, exact rendered content, recipient, signatory/delegation snapshot, official reference, document hash and issuance time. Do not reserve a final reference, create a public verification token, claim a digital signature or notify the applicant from a draft preview. The official reference format and signature mechanism need confirmation.

If public verification is approved, expose a minimal verification summary without the applicant's address, identity numbers, draft contents or the private PDF. Show superseded/revoked state correctly. The private document download still requires ownership or officer authorization. A document hash or QR code is not a digital signature.

Persist any notification/publication work through a transactional outbox or equivalent durable mechanism. File-render failure must leave an unpublished retryable operation, not an application labeled issued without an artifact. Successful retries must not duplicate official references, decisions, letters or notifications. Select and validate the PDF renderer during the issuance increment rather than installing the retired AIRS stack by default.

Letter layout follows the shared document direction: MBSP crest first, divider, iNDECS; Google Sans; Bahasa Melayu; approved title/recipient/subject, application and letter references, decision/reasons/conditions, signatory and attachments. Internal preview is prominently labelled `DRAF — BUKAN SURAT RASMI` and has no fabricated signature, seal or verification mark.

## Delivery increments

1. Internal recommendation history and draft letter previews, with scope, handover, concurrency and audit guards. No official state transitions, applicant delivery, approval grants or public verification.
2. Delegated decisions and immutable official issuance after policy/template confirmation. Integrate shared workflow history and recent staff authentication. Enable only confirmed outcomes.
3. Post-handover correction/resubmission, supersession/revocation and applicant notification/tracking as separately verified workflows. If Perlu pindaan is enabled in increment 2, its resubmission contract must move into that increment as a dependency.

## Departmental confirmations before official issuance

- Outcome names, approval conditions, whether a meeting decision is required and the actual delegation matrix (including separation of duties).
- Approved letter wording, reference numbering, recipient/CC rules, signatory details and signature method.
- Correction deadlines, extensions, resubmission acceptance, reassessment and fee treatment without breaking ADR-383.
- Retention, correction/revocation procedure, minimal public verification and applicant delivery channels.

Until confirmed, internal drafts may be developed and reviewed without treating this proposal as official JKEJ policy.
