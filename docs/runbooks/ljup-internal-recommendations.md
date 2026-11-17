# LJUP internal recommendations and draft letter previews

Decision: [ADR-392](../adr/ADR-392-ljup-officer-decisions-and-letters.md), increment 1. Official approval, signing and issuance are not implemented.

## Officer use

Open an LJUP application in `/kejuruteraan` and use `Syor dalaman & draf surat`. Both existing LJUP access and review permissions are required; department, active staff and application district restrictions apply in the service and routes. Access-only staff and applicants cannot view the recommendation history or draft previews.

The form becomes available after handover of a completed final AIRS check, with accepted-submission proof whose document-set hash matches that check. Select the proposed outcome, record reasons, proposed conditions and document/page/finding references, and explain missing required documents where flagged. Scores never block an internal recommendation or produce a decision.

Enter the proposed recipient, subject and draft wording. Leaving the letter wording blank uses the recommendation reasons. Saving creates a new attributed recommendation revision and its matching immutable draft-letter snapshot. Use history to inspect earlier revisions and their previews. There is no in-place edit/delete. The current revision is prefilled when preparing a new one.

The preview is labelled `DRAF — BUKAN SURAT RASMI`; its content and preparer name are frozen at save time. It is escaped plain text, with no HTML execution, official letter reference, signature, verification token, applicant download or notification. No application stage/status, stage history, AIRS score or AI finding is changed by preparation.

## Conflicts and failures

A stale recommendation revision, application/source change, new finding feedback or new profile review rejects the save with HTTP 409. Reload the record and review the latest information before saving again. Validation errors retain the original form's source/revision identifiers. An audit failure rolls back both recommendation and draft.

PostgreSQL row locks serialize concurrent editors against the review version, journey and application. Unique application/revision constraints provide a second guard. Source snapshots retain accepted submission, final review version, document manifest and the officer feedback/field-review records used for preparation. Draft records store a versioned internal template key, content snapshot and canonical SHA-256 hash.

## Storage and deployment

Additive migration: `2026_11_17_000200_create_internal_recommendation_drafts.php`. Tables: `core.application_recommendations` and `core.application_letter_drafts`. The shared `ApplicationRecommendationService` uses a `RecommendationSource` contract; `LjupRecommendationSource` supplies LJUP authorization, evidence and outcome labels.

Database triggers block update, delete and truncate. The migration refuses rollback while recommendation/draft records exist; preserve them during rollback or containment. Do not disable the triggers or delete audit data. No operational database migration was performed during implementation. Apply through the normal reviewed deployment process; no new role grants, external services or dependencies are required.

## Verification

Focused PostgreSQL suites: `php vendor/bin/phpunit tests/Feature/Kejuruteraan/InternalRecommendationTest.php tests/Concurrency/RecommendationConcurrencyTest.php`. Ten tests and 68 assertions pass, including two independent database writers contending on a real row lock (one save, one conflict), scope/ownership, escaping, frozen historical previews, stale feedback, low-score/missing-document handling, audit rollback and append-only guards.

Full PostgreSQL regression: 850 tests, 848 passed, two skipped, 4,151 assertions and zero failures. The guarded fresh/rollback/reapply/seed migration round-trip also passes on `indecs_test`. All 23 JavaScript tests, Pint, the production frontend build and diff whitespace checks pass. Synthetic officer form and draft-letter HTML were rendered in isolated browser profiles in light/dark mode; an exact 390px mobile viewport had no horizontal overflow. Hosted CI remains unavailable due to the GitHub account billing lock recorded in PR #2; this increment does not change account billing or claim a hosted pass.
