# AIRS as an iNDECS module — design

Date: 13 November 2026. Status: draft for owner approval.

## 1. Intent

iNDECS has taken over the public application portal. The standalone AIRS application (`C:\Users\Asyra\Documents\Plan Review`) is being scrapped. Its useful core moves **into the iNDECS codebase** as a module whose single job is:

> Check every submitted file, organise the documents for MATRIX, apply the department checklist to MATRIX's readings, and report the result back into the iNDECS application.

Why: one system, one identity, one database, one journey for applicants and officers. Running AIRS as a separate service with its own portal, accounts and billing duplicated what iNDECS already owns.

## 2. Goals

- One applicant journey in iNDECS, from draft to officer decision, with the AIRS check as a built-in step.
- Street-light applications (LJUP), owned by **Jabatan Kejuruteraan (JKEJ)**, are the first and only enabled module.
- Applicants get a paid technical check with visible findings and score **before** final submit, and one chance to amend.
- Officers in JKEJ receive the application with both check results and keep the per-finding comment workflow built in AIRS.
- Every AI call stays auditable: model, prompt version, raw and parsed response, latency, tokens and cost.

## 3. Non-goals

- No data migration from the AIRS database. The module starts clean.
- No new modules beyond LJUP (Bangunan/PKM rule packs come later, each with its own validation).
- No change to MATRIX's role or runtime beyond being called from iNDECS instead of AIRS.
- No rebuild of AIRS's staging billing, letters, SLA or round-robin assignment as part of this work. Those belong to iNDECS core work and may borrow ideas from the archived repo.
- No enforcement of the score threshold (see §5.4).

## 4. Anti-goals (failure even if it "works")

- AIRS or MATRIX closes, rejects or approves an application on its own.
- Paid AI (MATRIX/Claude) runs on an unpaid draft, or the free precheck reads drawings.
- An applicant can trigger more than the two paid checks per application.
- An officer comment overwrites the original AI finding instead of adding to its history.
- A finding marked `DITUNAIKAN` without at least one evidence record.
- A second identity, role or account system living inside the module.

## 5. Decided by the owner

### 5.1 Journey

1. **Draft + free precheck** — always available, unlimited. Checks only that uploaded documents fit the department checklist (attached, readable, right type). No content reading of drawings, no MATRIX. This is the existing iNDECS precheck (ADR-363) and stays as it is.
2. **Payment** — when the applicant is confident, they pay the fee. iNDECS records verified payment clearance.
3. **Check 1** — AIRS checks every file and organises the package; MATRIX reads the content; AIRS applies the LJUP checklist and calculates the score.
4. **Applicant sees check 1** — score, findings with page evidence, and any missing required documents.
5. **One amendment** — the applicant may resubmit **once** per application, within **5 working days** of check 1. The applicant may extend this once by another **5 working days**. If they submit without amending, **check 1 is final** and no check 2 runs.
6. **Check 2** (only if amended) — MATRIX runs again on the amended set. Applicant sees the result **read-only**; no further edits.
7. **Automatic handover** — the application passes to JKEJ officers with the check result(s) and amendment history.
8. **Officer process** — officers comment per finding, request corrections or decide, in iNDECS. **AIRS runs no further checks after handover**; officer corrections are reviewed by officers.
9. **Learning** — officer per-finding comments and field corrections are retained and made available to MATRIX as knowledge. Under MATRIX's existing rules, they are candidate lessons that pass its evaluation gate before changing behaviour; they do not retrain models automatically.

### 5.2 Fee

- One fee covers **two** MATRIX checks per application; the tariff must be based on twice the measured per-check cost (see `../MATRIX/docs/LJUP_COST_MEASUREMENT_PLAN.md`).
- Amount is not yet set. Corrections requested later by an officer remain a separate, undecided fee policy.

### 5.3 Missing required documents

- After check 1: shown to the applicant as an item to fix. The applicant can still proceed (an exemption may apply).
- After check 2: the application is **still accepted** and goes to the officer, clearly flagged *Dokumen wajib tidak dilampirkan*. The officer decides exemption or rejection.
- AIRS never closes an application.

### 5.4 Score threshold

- AIRS computes the score and records what a threshold *would* have decided ("shadow" result), per check.
- The applicant still sees their score.
- Enforcement stays **off** until the owner has confidence in the system; it is a configuration switch, off by default. When enabled later it needs its own ADR and still must not hide findings.

### 5.5 Placement

- AIRS code lives **inside the iNDECS repository and environment**. The standalone AIRS app, its HTTP API connector and its database content are scrapped.
- The Plan Review repo is kept archived as reference, not deleted.

### 5.6 Design calls made on the owner's behalf (owner to check)

- **Failed checks never use up a paid check.** Only a check that reaches `COMPLETED` counts toward the two. If MATRIX is down or a run fails, the system retries; staff can rerun; the applicant is told the check is still in progress. Rules MATRIX could not assess within the budget appear as `PERLU_SEMAKAN_MANUAL`, never as passed.
- **Checks belong to an `Application`, not a draft.** Verified payment converts the draft into a formal `Application` in a new "technical check" stage owned by JKEJ; checks 1 and 2 attach to it, each bound to its document-set version and hash. The `core.accepted_submissions` entry is written at handover (after the final check).
- **Amendment scope:** the applicant may replace, add or remove documents and edit particulars (including lighting types). Rule applicability is recalculated. Check 2 re-reads the whole set, matching the "twice the per-check cost" tariff.
- **Amendment window expiry:** if the window (including any extension) ends with no amendment, check 1 becomes final and the application hands over automatically. "Working days" uses the iNDECS working calendar (public holidays excluded); the builder adds one if none exists.
- **Check cap:** at most two completed checks per application, numbered 1 and 2; no later trigger (officer correction or otherwise) can start a third.
- **Applicant view:** rule title, status, standard comment and evidence page link, plus the score. `PERLU_SEMAKAN_MANUAL*` items appear as "Akan disemak oleh pegawai". Applicants do not see raw AI rationale, confidence, the shadow-threshold verdict or officer comments; those are officer-only.

## 6. What carries over from AIRS

| From AIRS (Plan Review) | Into iNDECS |
|---|---|
| `ArchiveExtractor`, `FileValidator`, `DocumentInventoryService`, `DocumentRelevanceService`, `DocumentClassifier` | Module document-preparation services |
| `python_worker/` (PyMuPDF/Pillow text, render, regions) and `PythonDocumentProcessor` | Worker inside the iNDECS repo |
| `ApplicationProfileExtractor` + `DeterministicProfileExtractor` | Profile extraction feeding the checklist |
| `RuleRepository`, `RuleApplicabilityService`, `RuleEvaluationService`, `EvidenceRetriever`, `FindingAggregator`, `FindingPolicy` | Checklist evaluation and findings |
| `knowledge/ljup/rules.v2.json` (32 active rules) + calibration + prompts | Module knowledge folder; still **unconfirmed by LJUP officers** |
| `ReadinessScorer` / `LiveReportScorer` (advisory wording) | Score per check + shadow threshold |
| `AiProvider` contract, `AiTaskRunner`, schemas/validator, `ai_runs` audit, `MatrixClaudeProvider` + `MatrixReviewGate` budgets, `FakeAiProvider` for tests | Same, called in-process from iNDECS |
| `ReviewFindingFeedback` (status, comment, history, evidence rule for `DITUNAIKAN`) | Officer per-finding comments, now **attributed to the iNDECS user** |
| `MatrixReviewController` field review (accept/correct/reject extracted fields) | Officer field review, attributed |
| Processing states `UPLOADED` … `COMPLETED`/`FAILED` and finding statuses | Unchanged vocabulary |
| Relevant tests (archive, inventory, relevance, aggregation, applicability, calibration, MATRIX, Green Camour acceptance) | Ported to iNDECS test suite against `indecs_test` |

## 7. What is scrapped

- AIRS users, roles, Google/magic-link login, companies, shadow accounts.
- AIRS public portal, upload page, standalone report pages and staging screens.
- AIRS billing (RM150/RM50 staging), dummy payments, bills, letters/QR verification, officer roster/SLA, notifications.
- The service API (`/api/v1/reviews`), `AuthenticateIndecs`, `IndecsIntake`, `indecs:recover-reviews`.
- On the iNDECS side: `AirsClient`, `airs:check`, `airs:sync`, `SyncAirsReview` and the HTTP-polling design (replaced by in-process jobs). `integration.airs_reviews`/`airs_feedback` are replaced by the module's own tables.
- The local AMIRA catalogue copy in AIRS (iNDECS has its own AMIRA integration).

## 8. Constraints that remain

- Never send an arbitrary archive straight to MATRIX; extract and validate first. Prevent ZIP path traversal and decompression bombs.
- Originals immutable; SHA-256 for every file; each check bound to an exact document-set version and hash.
- Document text is untrusted data, not instructions. Every AI response validated against a strict schema; invalid output cannot corrupt the review.
- Failed AI calls retried safely; overlap locks per check; queue separate from the public `precheck` queue.
- MATRIX only for paid LJUP checks, within per-request and per-check budgets; never from the free precheck.
- Model, provider, rule version, limits and feature flags in backend config; no keys in the browser.
- Findings advisory; only an authorised officer approves or rejects (unchanged by this design).
- iNDECS RBAC, department and district scopes govern who sees and comments on findings.
- Existing iNDECS conventions: ULIDs, schemas, append-only history triggers, `TestDatabaseSafety`.

## 9. Prerequisites outside this module

These are needed before the journey works end-to-end; they are iNDECS core work, not part of the module:

1. An **LJUP application type** under JKEJ, its document checklist and precheck keywords.
2. A path from **public draft to formal application** (does not exist today).
3. **Fee assessment and verified payment** (no fee/payment tables or gateway exist today).
4. JKEJ officer roles/permissions for LJUP review.

The module can be built and tested before these exist, using test fixtures for payment clearance and the fake AI provider.

## 10. Documentation changes

- New iNDECS ADR: AIRS merged into iNDECS as a module; paid check before final submit; two checks and one amendment; applicant sees findings and score after payment; score threshold as a future switch. Supersedes the "separate service, API-only" parts of ADR-352/ADR-354 and the AIRS contract (`docs/integration/indecs-airs-contract.md`, Plan Review doc 41/43).
- Update both `AGENTS.md` files and the iNDECS runbook; mark the Plan Review repo as archived.
- Update MATRIX `INTEGRATION_BOUNDARIES.md` and `RUNTIME_SETUP.md`: the client is now iNDECS.

## 11. Left to the builder

- Module folder layout and naming inside `app/`, table names within the `airs` schema, and how the module's job flow maps to iNDECS queues.
- How check 1/check 2 records relate to the existing `core.accepted_submissions` ledger (its meaning moves: acceptance now follows check 2, while payment clearance precedes check 1).
- Applicant and officer screen layout, following iNDECS Blade/Tailwind conventions and the precheck labels.
- How the Python worker and the MATRIX Node runtime are installed and supervised in the iNDECS environment.
- Order of removal for the iNDECS HTTP connector code (uncommitted today) versus building the module.

## 12. Known state to handle first

- The iNDECS working tree on `feat/kaunter-maya-shell` has ~209 uncommitted changes, including the AIRS connector; the Plan Review repo has ~64 uncommitted files including the MATRIX provider. **Owner decision: commit both repos as they are first** (iNDECS on its current branch), then build the module on top; the iNDECS HTTP connector is removed afterwards in a normal commit. Review the staged files for secrets and bulky runtime folders before committing.
- LJUP rules (v2) are a starter set calibrated on one case and not confirmed by LJUP officers.
- No OCR exists in the AIRS worker; iNDECS precheck has local Tesseract OCR that may be reused.
