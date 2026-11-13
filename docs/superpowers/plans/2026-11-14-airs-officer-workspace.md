# AIRS Officer Workspace (Plan 3 of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Jabatan Kejuruteraan (JKEJ) officers an iNDECS workspace for LJUP applications: see the AIRS check results with full evidence, record attributed per-finding comments and profile-field corrections, grant an audited fee waiver, and export officer corrections to MATRIX as candidate lessons. Close the merge with the superseding ADR and documentation.

**Architecture:** A new `kejuruteraan` area in iNDECS (`/kejuruteraan`, middleware `kejuruteraan.access` on permission `kejuruteraan.ljup.access`). Officer reads go through an `OfficerCheckPresenter` (full detail, unlike the applicant presenter). Officer writes are append-only rows in `airs.review_finding_feedbacks` and `airs.profile_field_reviews`, attributed to the iNDECS user and never altering the original AI finding; a ported `LiveReportScorer` shows the officer-adjusted score beside the original. Candidate lessons are exported as de-identified JSONL to a local folder for MATRIX's evaluation process (MATRIX's own repo holds no applicant data).

**Tech Stack:** Laravel 13.8, PHP 8.3, PostgreSQL (`indecs_test`), Blade, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-11-13-airs-module-merge-design.md` (§5.1 step 8–9, §5.6, §6 carry-over rows for feedback and field review, §10 documentation). Builds on Plans 1–2.

**Not in this plan:** official decisions, correction requests and letters (iNDECS core work outside AIRS), real payment gateway, technical circulation to other departments, a waiver screen for applicants to request waivers (policy undecided).

## Global Constraints

- Officers act only with permission `kejuruteraan.ljup.review` (comments, field review) or `kejuruteraan.ljup.fee_waive` (waiver, new, granted to `senior_engineering_officer` and `system_admin`); reading needs `kejuruteraan.ljup.access`.
- District scope (ADR-307): an officer with a district sees an LJUP application when one of its sites/lots is in that district, **or** when the application has no site/lot rows (journey applications carry only a free-text address) — record this as a ruling if changed.
- Officer feedback is append-only (DB triggers using `core.prevent_history_mutation()`), attributed (`actor_id` NOT NULL), and never updates `airs.review_findings`. `DITUNAIKAN` requires at least one evidence record on the finding.
- Officers comment only on the **final** check of a handed-over journey (`PaidCheckState::HandedOver`, `final_check_number`). Earlier checks are read-only history.
- Officers see everything applicants cannot: AI finding text, confidence, manual-review reason, warnings, shadow outcome (labelled `Keputusan bayangan — tidak dikuatkuasakan`), MATRIX usage. The original AI status and the officer status are shown side by side.
- No score, shadow outcome or feedback changes `applications.current_stage/current_status` (officer decisions are core iNDECS work, outside this plan).
- Lesson export contains no applicant names, e-mails, addresses, application references, document file names or file bytes. Applications are identified by `sha256(app_key + application_id)`. Each record has `status: "CANDIDATE_UNEVALUATED"`.
- Existing finding statuses only: `DITUNAIKAN, BELUM_DITUNAIKAN, TIDAK_BERKAITAN, TIDAK_DAPAT_DIKESAN, PERLU_SEMAKAN_MANUAL, PERLU_SEMAKAN_MANUAL_PEGAWAI`.

## Review Focus

1. An officer from another department (JBANG) or a public applicant opens the workspace, a finding comment route, or an evidence document — must be 403/404 (Task 1 test `other_departments_and_public_users_are_refused`).
2. An officer comments on check 1 after check 2 is final, or on a journey not yet handed over — must be refused (Task 2 test `comments_only_on_final_check_of_handed_over_journey`).
3. Two officers record field corrections at the same time on the same field — the stale one must get 409, not overwrite (Task 3 test `stale_revision_is_refused`).
4. A waiver for a draft that already has a paid payment or journey — must be refused, not create a second application (Task 4 test `waiver_refused_when_already_paid`).
5. Lesson export run twice — must not duplicate records already exported, and must not leak applicant identifiers (Task 5 tests `export_is_incremental`, `export_contains_no_identifiers`).

---

### Task 1: Kejuruteraan workspace — list and check detail

**Files:**
- Create: `app/Http/Middleware/EnsureKejuruteraanAccess.php`, `app/Http/Controllers/KejuruteraanApplicationController.php`, `app/Services/Kejuruteraan/OfficerCheckPresenter.php`, `resources/views/kejuruteraan/index.blade.php`, `resources/views/kejuruteraan/show.blade.php`
- Modify: `app/Models/User.php` (`canAccessKejuruteraan(): bool`, `canPerformLjup(string $action): bool` → `userCan($this, 'kejuruteraan.ljup.'.$action, 'JKEJ', 'LJUP')`), `bootstrap/app.php` (alias `kejuruteraan.access`), `routes/web.php`, `app/Services/ApplicationLauncher.php` (Kejuruteraan tile → `route('kejuruteraan.index')` when `canAccessKejuruteraan()`; AIRS tile → same URL for administrators), `app/Models/Application.php` (`scopeVisibleTo` rule above, LJUP only — do not change behaviour for other types)
- Test: `tests/Feature/Kejuruteraan/OfficerWorkspaceTest.php`

**Interfaces:**
- Routes (prefix `/kejuruteraan`, name `kejuruteraan.`, middleware `auth`, `kejuruteraan.access`): `GET /` (`index`), `GET /applications/{application}` (`show`), `GET /applications/{application}/documents/{document}` (`documents.show`, streams an `ApplicationDocument` of that application, inline).
- `OfficerCheckPresenter::for(Application $application): array` returning `['journey' => ?PaidCheckJourney, 'checks' => list<['number','status','score','officer_score','shadow_outcome','error_code','items' => list<[finding_id, rule_id, section, title, requirement, ai_status, officer_status, officer_comment, feedback_history, confidence, finding, manual_review_reason, standard_comment, evidence => list<[document_id, document, page, quote]>]>]>, 'final_check_number' => ?int, 'missing_required' => list<string>, 'usage' => array]`. `officer_score` comes from the ported `LiveReportScorer` (Task 2); until then equal to `score`.
- Index lists LJUP applications with: reference, title, stage label, journey state label, final check score, count of findings needing officer review (`PERLU_SEMAKAN_MANUAL*` without feedback), missing-documents flag. Default filter: handed over first, newest first; paginate 25.

- [ ] **Step 1: Write the failing tests**

```php
test_engineering_officer_sees_ljup_applications()             // handed-over journey fixture → index shows reference + 'Diserahkan kepada Jabatan Kejuruteraan'
test_show_displays_ai_detail_and_shadow_outcome()               // finding text, confidence %, 'Keputusan bayangan — tidak dikuatkuasakan'
test_show_lists_missing_required_documents()                   // 'Dokumen wajib tidak dilampirkan' + name
test_officer_downloads_evidence_document()                     // 200, bytes match
test_other_departments_and_public_users_are_refused()          // building_officer → 403 on index/show/document; applicant user → redirect/403
test_document_of_another_application_is_not_found()           // 404
test_district_officer_sees_ljup_application_without_sites()   // officer with district 'SPT' sees it
test_launcher_shows_kejuruteraan_for_engineering_officer()
```

Use a shared trait `tests/Feature/Kejuruteraan/BuildsHandedOverLjup.php` that reuses `Tests\Feature\PaidCheck\BuildsLjupJourney`, pays, runs check 1 (sync, fake provider, `FakeAirsDocumentProcessor`) and submits as-is; returns `[$application, $journey]`.

- [ ] **Step 2: Run** `php artisan test --filter=OfficerWorkspaceTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="OfficerWorkspaceTest|ApplicationLauncherTest|CoreRbac"` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Kejuruteraan workspace: LJUP applications with AIRS check detail"`

---

### Task 2: Attributed per-finding officer comments and officer-adjusted score

**Files:**
- Create: `database/migrations/2026_11_16_000100_create_airs_officer_feedback.php`, `app/Airs/Models/ReviewFindingFeedback.php`, `app/Airs/Review/FindingFeedbackService.php`, `app/Airs/Review/LiveReportScorer.php` (ported from `AIRS:app/Services/Review/LiveReportScorer.php`, without any `rejected` key or `isRejected` call)
- Modify: `app/Airs/Models/ReviewFinding.php` (`feedbacks(): HasMany` oldest first), `app/Http/Controllers/KejuruteraanApplicationController.php` (`storeFeedback`), `routes/web.php`, `resources/views/kejuruteraan/show.blade.php`, `app/Services/Kejuruteraan/OfficerCheckPresenter.php`
- Test: `tests/Feature/Kejuruteraan/FindingFeedbackTest.php`, `tests/Unit/Airs/LiveReportScorerTest.php`

**Interfaces:**
- Table `airs.review_finding_feedbacks`: `id` ulid, `review_finding_id` FK (restrict), `actor_id` FK users NOT NULL, `status` string, `comment` text nullable (max 2000 in validation), `created_at` timestampTz. Triggers: `BEFORE UPDATE OR DELETE … EXECUTE FUNCTION core.prevent_history_mutation()` and a `BEFORE TRUNCATE` statement trigger, matching the pattern in `2026_09_21_000700_enforce_append_only_audit_and_history.php`.
- `FindingFeedbackService::record(User $officer, ReviewFinding $finding, string $status, ?string $comment): ReviewFindingFeedback` — requires `canPerformLjup('review')`; finding's version is the journey's final check and journey `HandedOver` (else `ValidationException` key `feedback`: `Ulasan hanya untuk semakan muktamad yang telah diserahkan.`); status in the enum; `DITUNAIKAN` requires `$finding->evidence()->exists()` (`Bukti diperlukan untuk status Ditunaikan.`); writes AuditLog `airs_finding_feedback`.
- Route `POST /kejuruteraan/applications/{application}/findings/{finding}/feedback` (`kejuruteraan.findings.feedback`, throttle 30/min).
- `LiveReportScorer::apply(array $report, Collection $feedbackByFinding): array` — returns report with each finding's `ai_status`, `status` (latest officer status if any), `officer_confirmed`, and recomputed `application_score`/`section_checklist` via `ReadinessScorer`.

- [ ] **Step 1: Write the failing tests**

```php
test_officer_comment_is_attributed_and_keeps_ai_finding()      // feedback actor = officer; review_findings.status unchanged; page shows both statuses
test_ditunaikan_requires_evidence()                             // finding without evidence → error
test_comments_only_on_final_check_of_handed_over_journey()     // not handed over → error; after check 2 final, finding of check 1 → error
test_feedback_rows_cannot_be_updated_or_deleted()               // DB::table update → QueryException
test_officer_score_reflects_latest_feedback()                   // mark a manual item DITUNAIKAN (with evidence) → officer_score > score
test_building_officer_cannot_comment()                          // 403
// Unit: LiveReportScorer keeps ai_status, uses latest of several entries, never emits 'rejected'
```

- [ ] **Step 2: Run** `php artisan test --filter="FindingFeedbackTest|LiveReportScorerTest"` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="Kejuruteraan|Airs"` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Attributed append-only officer comments per AIRS finding; officer-adjusted score"`

---

### Task 3: Profile field review (MATRIX extracted fields)

**Files:**
- Create: `database/migrations/2026_11_16_000200_create_airs_profile_field_reviews.php`, `app/Airs/Models/ProfileFieldReview.php`, `app/Airs/Review/ProfileFieldReviewService.php`
- Modify: `app/Airs/Review/MatrixProfileProjection.php` (import `App\Airs\Models\ProfileFieldReview` — today it points at a class that does not exist in iNDECS), `KejuruteraanApplicationController` (`storeFieldReview`), `routes/web.php`, `resources/views/kejuruteraan/show.blade.php` (field table: extracted value, source page, latest disposition, form)
- Test: `tests/Feature/Kejuruteraan/ProfileFieldReviewTest.php`

**Interfaces:**
- Table `airs.profile_field_reviews` (as AIRS `2026_11_13_000100`): `id` ulid, `review_version_id` FK, `actor_id` FK users NOT NULL, `field` string, `disposition` (`ACCEPTED|CORRECTED|REJECTED` CHECK), `value` text nullable, `reason` text, `revision` int, `created_at`; unique `(review_version_id, field, revision)`; append-only triggers as Task 2.
- `ProfileFieldReviewService::record(User $officer, ReviewVersion $version, string $field, string $disposition, ?string $value, string $reason, int $expectedRevision): ProfileFieldReview` — same rules as `AIRS:app/Http/Controllers/MatrixReviewController.php::store` (field in `MatrixProfileProjection::FIELDS`; version is the final check and `COMPLETED`; `expectedRevision` must equal the current max revision else 409 `Profil telah dikemas kini; muat semula.`; `ACCEPTED` needs an extracted value and field evidence; `CORRECTED` needs a non-blank value), plus `canPerformLjup('review')` and handed-over journey. Never changes official application particulars.
- Route `POST /kejuruteraan/applications/{application}/fields` (`kejuruteraan.fields.review`).

- [ ] **Step 1: Write the failing tests**

```php
test_officer_corrects_a_field_with_reason()          // revision 1, actor recorded, application title unchanged
test_stale_revision_is_refused()                      // expected_revision 0 after a revision exists → 409
test_accept_requires_extracted_value_and_evidence()
test_projection_uses_latest_field_review()           // MatrixProfileProjection::apply reflects CORRECTED value and marks dependencies stale
```

- [ ] **Step 2: Run** `php artisan test --filter=ProfileFieldReviewTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="Kejuruteraan|Airs"` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Officer review of MATRIX-extracted profile fields"`

---

### Task 4: Audited fee waiver

**Files:**
- Create: `database/migrations/2026_11_16_000300_add_waiver_to_check_payments.php`, `app/Console/Commands/WaivePaidCheckFee.php`
- Modify: `app/Services/PaidCheck/CheckPaymentService.php` (`waive`), `app/Services/PaidCheck/PaidCheckJourneyService.php` (clearance kind from the payment), `app/Models/CheckPayment.php`, LJUP permission migration pattern for `kejuruteraan.ljup.fee_waive` (new migration in this task's file), `database/seeders/DatabaseSeeder.php` (system_admin list)
- Test: `tests/Feature/PaidCheck/FeeWaiverTest.php`

**Interfaces:**
- Columns on `core.check_payments`: `clearance_kind` (`paid|waived`, default `paid`), `waived_by` FK users nullable, `waiver_reason` text nullable; CHECK: `clearance_kind = 'waived'` ⇒ `waived_by` and `waiver_reason` not null and `amount_sen = 0`.
- Permission `kejuruteraan.ljup.fee_waive` → `senior_engineering_officer` (JKEJ/LJUP) and `system_admin` (unscoped).
- `CheckPaymentService::waive(User $officer, ApplicantDraft $draft, string $reason): CheckPayment` — requires the permission; draft type has an enabled AIRS module; no `paid` payment and no journey for the draft (`ValidationException` `payment`); reason ≥ 10 characters (approved-policy reference expected in it); cancels any pending payment (`status cancelled`); creates a `paid` row with `gateway 'waiver'`, `clearance_kind 'waived'`, `amount_sen 0`, `gateway_event_id 'waiver-'.ULID`, `paid_at now()`; AuditLog `check_payment_waived`; dispatches `CheckPaymentConfirmed`.
- `PaidCheckJourneyService::startCheck` uses `new CheckClearance($payment->clearance_kind, …)`; handover writes `clearance_kind` and `cleared_by = waived_by` to `core.accepted_submissions` (its CHECK requires `cleared_by` for waivers).
- Artisan `paid-check:waive {draft} --actor= --reason=` (no officer screen yet: drafts are applicant-owned and officers have no draft list; the command is the audited path until a waiver-request flow is designed).

- [ ] **Step 1: Write the failing tests**

```php
test_senior_engineer_waives_fee_and_check_runs()       // journey created, accepted_submissions clearance_kind 'waived', cleared_by officer after handover
test_engineering_officer_without_permission_is_refused()
test_waiver_refused_when_already_paid()                 // paid draft → ValidationException, still one journey
test_waiver_cancels_pending_payment()
test_waiver_reason_required()
```

- [ ] **Step 2: Run** `php artisan test --filter=FeeWaiverTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter=PaidCheck` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Audited fee waiver for the paid AIRS check"`

---

### Task 5: Candidate-lesson export for MATRIX

**Files:**
- Create: `database/migrations/2026_11_16_000400_create_airs_lesson_exports.php`, `app/Airs/Knowledge/LessonExporter.php`, `app/Airs/Console/ExportAirsLessons.php`
- Modify: `config/airs.php` (`matrix.lessons_path` = `env('AIRS_MATRIX_LESSONS_PATH', storage_path('app/airs/lessons'))`), `app/Airs/AirsServiceProvider.php` (register command), `.env.example`
- Test: `tests/Feature/Airs/LessonExportTest.php`

**Interfaces:**
- Table `airs.lesson_exports`: `source_type` (`finding_feedback|field_review`), `source_id` ulid, `exported_at`, `file`; primary key (`source_type`, `source_id`).
- `LessonExporter::export(?CarbonImmutable $since = null): array{file: ?string, count: int}` — selects feedback where officer status ≠ AI status or comment not blank, and field reviews with disposition `CORRECTED|REJECTED`, not yet in `airs.lesson_exports`; writes one JSONL file `lessons-{Ymd-His}-{ulid}.jsonl` under `lessons_path`; records each exported source. Record shape:

```json
{"lesson_id":"<ulid>","status":"CANDIDATE_UNEVALUATED","kind":"finding_correction|field_correction",
 "case_key":"<sha256(app.key + application_id)>","module":"STREET_LIGHTING","rule_pack":"ljup","rule_version":"v2",
 "prompt_version":"v1","ai_provider":"matrix","model":"...","check_number":1,
 "rule_id":"LJUP-...","ai_status":"PERLU_SEMAKAN_MANUAL","officer_status":"DITUNAIKAN","officer_comment":"...",
 "field":null,"ai_value":null,"officer_value":null,
 "evidence":[{"document_type":"LJUP_PLAN","page":2}],"recorded_at":"2026-11-16T03:00:00Z"}
```

  No application reference, names, e-mails, addresses, file names, file paths or bytes. Officer comments are free text and may still contain names — the command prints a reminder that files are for the MATRIX evaluation team only.
- Artisan `airs:export-lessons {--since=}` prints the file path and count.

- [ ] **Step 1: Write the failing tests**

```php
test_export_writes_candidate_lessons_for_officer_corrections()   // one feedback differing from AI → 1 JSONL line with status CANDIDATE_UNEVALUATED
test_matching_feedback_without_comment_is_not_exported()
test_export_is_incremental()                                       // second run → count 0, no new file
test_export_contains_no_identifiers()                              // file text contains neither application_reference, title, applicant e-mail, document original_name, nor storage path
test_field_corrections_are_exported()
```

- [ ] **Step 2: Run** `php artisan test --filter=LessonExportTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** same filter — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Export officer corrections as candidate lessons for MATRIX evaluation"`

---

### Task 6: Superseding ADR and documentation

**Files:**
- Create: `docs/adr/ADR-383-airs-merged-into-indecs.md`
- Modify (iNDECS): `docs/adr/README.md` (index entry), `docs/adr/ADR-352-*.md` and `docs/adr/ADR-354-*.md` (status line: superseded in part by ADR-383), `docs/integration/indecs-airs-contract.md` (banner: superseded by ADR-383 where it describes a separate service/API), `docs/runbooks/airs-integration.md` (rewrite for the in-process module: workers, scheduler entries `airs:recover-checks`, `paid-check:hand-over-expired`, `paid-check:stuck`, `airs:export-lessons`, `matrix:status`, env keys incl. `DB_TIMEZONE`, the old-AIRS-tables cleanup note), `.env.example` (`DB_TIMEZONE=UTC`), `AGENTS.md` and `README.md` (AIRS lines: Plans 1–3 delivered; real gateway and officer decisions/letters remain)
- Modify (Plan Review repo, commit there): `README.md` and `AGENTS.md` — top banner "Archived 2026-11: AIRS now lives in iNDECS (`app/Airs`); this repository is reference only."
- Modify (MATRIX folder, not a git repo — edit only): `docs/INTEGRATION_BOUNDARIES.md`, `docs/RUNTIME_SETUP.md` — the client is now the iNDECS AIRS module; worker path configured by `AIRS_MATRIX_WORKER`; candidate lessons arrive as JSONL from `airs:export-lessons`.
- Test: `tests/Feature/Airs/DocumentationTest.php`

**Interfaces:**
- ADR-383 records the owner decisions from the spec: AIRS code merged into iNDECS; standalone AIRS retired; paid check before final submit with two checks and one amendment (5 + 5 working days); applicant sees findings and score after paying; missing documents flagged not rejected; score threshold shadow-only until a later ADR; MATRIX lessons are candidates pending evaluation; payment gateway and fee amount still open.

- [ ] **Step 1: Write the failing test**

```php
test_adr_383_exists_and_is_indexed()                 // file exists; docs/adr/README.md mentions ADR-383
test_runbook_no_longer_describes_http_connector()    // airs-integration.md does not contain 'AIRS_SERVICE_TOKEN' or 'airs:sync'; contains 'airs:recover-checks'
test_env_example_documents_db_timezone()
```

- [ ] **Step 2: Run** `php artisan test --filter=DocumentationTest` — Expected: FAIL.
- [ ] **Step 3: Write the documents.**
- [ ] **Step 4: Run** `php artisan test` — Expected: full suite PASS.
- [ ] **Step 5: Commit** — iNDECS: `git commit -m "ADR-383: AIRS merged into iNDECS; runbook and docs"`; Plan Review: `git commit -m "Archive notice: AIRS now lives in iNDECS"`.
