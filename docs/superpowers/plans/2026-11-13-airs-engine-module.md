# AIRS Engine Module (Plan 1 of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the AIRS review engine (document preparation, MATRIX/AI layer, LJUP checklist evaluation, scoring) into the iNDECS codebase as an in-process module that can run check 1 and check 2 for a payment-cleared iNDECS `Application`.

**Architecture:** Port AIRS services into a self-contained `App\Airs` namespace (`app/Airs/`) with its tables in the PostgreSQL `airs` schema. One `airs.review_cases` row per iNDECS application; each check is one `airs.review_versions` row (`check_number` 1 or 2) whose files carry `submission_number = check_number`. A `CheckService` is the only entry point; it snapshots `ApplicationDocument` originals, enforces the two-check cap, and dispatches the ported pipeline job on the `airs` queue. The old HTTP connector between iNDECS and the standalone AIRS app is removed.

**Tech Stack:** Laravel 13.8, PHP 8.3, PostgreSQL 16 (`indecs_test` for tests), PHPUnit 12, Python 3 worker (PyMuPDF, Pillow), MATRIX Node runtime (`../MATRIX/runtime/cli.mjs`).

**Spec:** `docs/superpowers/specs/2026-11-13-airs-module-merge-design.md`

**Later plans (not in this plan):** Plan 2 — applicant check journey (LJUP application type under JKEJ, payment → application, check 1, amendment window 5+5 working days, check 2, handover, applicant view). Plan 3 — officer workspace (attributed per-finding comments, field review, MATRIX knowledge retention), ADR and documentation changes, archiving the Plan Review repo.

**Source repo for porting:** `C:\Users\Asyra\Documents\Plan Review` (called *AIRS* below). Paths prefixed `AIRS:` are there; all other paths are in `C:\Users\Asyra\Documents\iNDECS`.

## Global Constraints

- Port rule (applies to every ported file): namespace map
  `App\Services\DocumentProcessing\*` → `App\Airs\DocumentProcessing\*`;
  `App\Services\Review\*` → `App\Airs\Review\*`;
  `App\Services\Ai\*` → `App\Airs\Ai\*`;
  engine models `App\Models\*` → `App\Airs\Models\*`;
  `App\Enums\{ReviewCaseStatus,FindingStatus,EvaluationMethod}` → `App\Airs\Enums\*`;
  `App\Contracts\{AiProvider,DocumentProcessor}` → `App\Airs\Contracts\*`;
  `App\Exceptions\{InvalidAiResponseException,InvalidDocumentException,MatrixTaskException,NoRelevantDocumentException,UnsafeArchiveException}` → `App\Airs\Exceptions\*`.
- Config map: `config('plan_review.X')` → `config('airs.X')`; `config('matrix.X')` → `config('airs.matrix.X')`.
- Strip every reference to AIRS billing, submission status, applicant users, companies, assignment, letters, notifications, `integration_*` fields and `IndecsIntake`. No ported file may reference `BillingService`, `SubmissionStatus`, `ReviewCase::billing_required` or AIRS `User`.
- Every engine model sets `protected $table = 'airs.<table>'` and uses `HasUlids`.
- Processing states unchanged: `UPLOADED, EXTRACTING, INVENTORY, EXTRACTING_TEXT, RENDERING, CLASSIFYING, EXTRACTING_PROFILE, EVALUATING_RULES, CONSOLIDATING, GENERATING_REPORT, COMPLETED, FAILED`.
- Finding statuses unchanged: `DITUNAIKAN, BELUM_DITUNAIKAN, TIDAK_BERKAITAN, TIDAK_DAPAT_DIKESAN, PERLU_SEMAKAN_MANUAL, PERLU_SEMAKAN_MANUAL_PEGAWAI`. No `DITUNAIKAN` without at least one evidence record.
- At most **two completed checks** per application; a failed check never counts.
- MATRIX runs only for module `STREET_LIGHTING`, only on a case with recorded payment clearance, within `airs.matrix.request_budget_usd` (0.25) and `airs.matrix.review_budget_usd` (5).
- Scores are advisory. AIRS never changes `applications.current_stage`/`current_status`. The score threshold produces a recorded *shadow* outcome only; no enforcement code path exists.
- Tests use `AIRS_AI_PROVIDER=fake`, `AIRS_MATRIX_ENABLED=false`, `Http::preventStrayRequests()`, and `indecs_test`. No test calls a paid provider.

## Review Focus

1. An `ApplicationDocument` whose stored bytes no longer match `checksum_sha256` — `CheckService::start` must refuse (Task 7 test `refuses_document_whose_bytes_changed`).
2. A double-clicked/duplicated start while a check is queued or running — must not create a second version (Task 7 test `refuses_second_start_while_check_in_progress`).
3. Rerun after a failed check — must reuse the same `check_number`, not consume a check (Task 7 test `failed_check_is_rerun_without_consuming_a_check`).
4. MATRIX budget exhausted mid-check — affected rules must become `PERLU_SEMAKAN_MANUAL` and the check still completes (Task 6 test `budget_exhausted_rule_becomes_manual_review`).
5. An application whose type has no enabled module (e.g. `PBK`) — must be refused, not silently use LJUP rules (Task 7 test `refuses_application_type_without_enabled_module`).

---

### Task 1: Commit both repositories as they are

**Files:** none created; repository state only.

- [ ] **Step 1: Review AIRS working tree**

Run (in `C:\Users\Asyra\Documents\Plan Review`): `git status --short`
Check untracked/modified paths. Do **not** stage `.env`, `test_subject/`, `tmp/`, `Temporary/`, `storage/app/**` runtime data, `output/`, `outputs/` or `node_modules/`. If any of these show as untracked, add them to `.gitignore` in this commit.

- [ ] **Step 2: Scan staged diff for secrets**

Run: `git add -A && git diff --cached | grep -nEi "(sk-[a-z0-9]{20}|api[_-]?key\s*=\s*\S{12}|token\s*=\s*\S{24}|password\s*=\s*\S{6})"`
Expected: no matches other than `.env.example` placeholders. Unstage any real secret.

- [ ] **Step 3: Commit AIRS**

```bash
git commit -m "Snapshot before merge into iNDECS: iNDECS connector, MATRIX provider, AMIRA catalogue, advisory scoring, docs"
```

- [ ] **Step 4: Repeat Steps 1–2 in iNDECS** (branch `feat/kaunter-maya-shell`; same exclusions plus `database/database.sqlite`).

- [ ] **Step 5: Commit iNDECS**

```bash
git commit -m "Snapshot before AIRS module merge: AIRS connector, AMIRA, portal and precheck work"
```

- [ ] **Step 6: Verify** — `git status --short` in both repos shows nothing except ignored files.

---

### Task 2: Remove the HTTP connector from iNDECS

**Files:**
- Delete: `app/Services/Airs/AirsClient.php`, `app/Services/Airs/AirsReviews.php`, `app/Jobs/SyncAirsReview.php`, `app/Console/Commands/AirsConnectionCheck.php`, `app/Console/Commands/SyncAirsReviews.php`, `app/Http/Controllers/AirsReviewController.php`, `app/Models/AirsReview.php`, `app/Models/AirsFeedback.php`, `resources/views/airs/`, `tests/Feature/AirsIntegrationTest.php`, `config/airs.php`
- Keep: `app/Models/AcceptedSubmission.php`, `app/Services/Airs/DocumentManifest.php` (move to `app/Airs/DocumentManifest.php`, namespace `App\Airs`)
- Modify: `routes/web.php` (remove `/airs` and `/applications/{id}/airs` routes, lines ~27–33), `routes/console.php` (remove `airs:sync` schedule), `.env.example` (remove `AIRS_SERVICE_URL`, `AIRS_SERVICE_TOKEN`), `phpunit.xml` (remove `AIRS_ENABLED`), any nav link to `airs.index`
- Create: `database/migrations/2026_11_14_000100_drop_airs_http_connector_tables.php`
- Test: `tests/Feature/Airs/ConnectorRemovedTest.php`

**Interfaces:**
- Produces: `App\Airs\DocumentManifest::forApplication(Application $app): array` and `DocumentManifest::hash(array $manifest): string` (unchanged behaviour, new namespace).

- [ ] **Step 1: Write the failing test**

```php
public function test_connector_routes_and_tables_are_gone(): void
{
    $this->assertFalse(Route::has('airs.index'));
    $this->assertFalse(Schema::hasTable('integration.airs_reviews'));
    $this->assertFalse(Schema::hasTable('integration.airs_feedback'));
    $this->assertTrue(Schema::hasTable('core.accepted_submissions'));
    $this->assertArrayNotHasKey('airs:sync', Artisan::all());
}
```

- [ ] **Step 2: Run** `php artisan test --filter=ConnectorRemovedTest` — Expected: FAIL.
- [ ] **Step 3: Delete/modify files listed above; write the migration**: `up()` drops `integration.airs_feedback` then `integration.airs_reviews`; `down()` recreates them exactly as in `2026_11_04_000100_create_airs_integration.php`.
- [ ] **Step 4: Run** `php artisan test` — Expected: whole suite PASS (fix any remaining references found by `grep -rn "AirsReview\|AirsClient\|airs\.url" app routes resources tests config`).
- [ ] **Step 5: Commit** — `git commit -m "Remove AIRS HTTP connector; AIRS moves in-process"`

---

### Task 3: Module foundation — config, schema, models, provider binding

**Files:**
- Create: `config/airs.php`, `app/Airs/AirsServiceProvider.php`, `app/Airs/Contracts/AiProvider.php`, `app/Airs/Contracts/DocumentProcessor.php`, `app/Airs/Enums/{ReviewCaseStatus,FindingStatus,EvaluationMethod}.php`, `app/Airs/Exceptions/*` (5 files, ported), `app/Airs/Models/{ReviewCase,ReviewVersion,ReviewFile,ReviewPage,PageRegion,DocumentClassification,ApplicationProfile,ReviewRule,ReviewFinding,ReviewEvidence,AiRun,ProcessingTask,GeneratedReport,SystemConfigSnapshot}.php`
- Create: `database/migrations/2026_11_14_000200_create_airs_module_tables.php`
- Modify: `bootstrap/providers.php` (register `AirsServiceProvider`), `phpunit.xml` (add `AIRS_AI_PROVIDER=fake`, `AIRS_MATRIX_ENABLED=false`, both `force="true"`), `.env.example` (add the `AIRS_*` keys below)
- Test: `tests/Feature/Airs/ModuleFoundationTest.php`, port `AIRS:tests/Unit/DomainEnumTest.php` → `tests/Unit/Airs/DomainEnumTest.php`

**Interfaces:**
- Produces: `config('airs')` with keys: `provider` (env `AIRS_AI_PROVIDER`, default `fake`), `text_model`, `vision_model`, `rule_version` (`v2`), `prompt_version` (`v1`), `report_version` (`v1`), `queue` (`airs`), `knowledge_path` (`base_path('knowledge')`), `python` (env `AIRS_PYTHON`, default `python`), `python_worker` (`base_path('python_worker')`), `modules` = `['LJUP' => ['code' => 'STREET_LIGHTING', 'rule_pack' => 'ljup', 'enabled' => true]]`, `lighting_types` (copy from `AIRS:config/plan_review.php`), `score_threshold` (env `AIRS_SCORE_THRESHOLD`, default = the value used by `AIRS:ReadinessScorer::isRejected`), `upload limits` (copy the AIRS limit keys), `matrix` = contents of `AIRS:config/matrix.php` with env prefix `AIRS_MATRIX_`.
- Produces: `App\Airs\Contracts\AiProvider` (same 4 methods as AIRS) bound as singleton by `provider` value: `fake` → `FakeAiProvider`, `openai` → `OpenAiProvider`, `matrix` → `MatrixClaudeProvider` (the classes arrive in Task 5; bind by class-string so the provider file compiles now).
- Produces tables in schema `airs` (create with `CREATE SCHEMA IF NOT EXISTS airs`). Columns are the final AIRS shapes (`AIRS:database/migrations/2026_07_16…`, `2026_07_27…`, `2026_09_10…` for `review_files.submission_number`, `2026_11_13…` for `ai_runs.usage_json/coverage_json`) **with these differences**:
  - `airs.review_cases`: keep `id, review_module_code, selected_lighting_types, document_set_version, status, progress, current_step, file_count, page_count, total_original_bytes, total_extracted_bytes, error_code, error_message, warnings, started_at, completed_at, timestamps`; add `application_id` (ULID FK `applications`, unique, restrictOnDelete), `clearance_kind` (`paid|waived`), `payment_clearance_reference`, `payment_cleared_at`; drop all billing/applicant/submission/integration/company/assignment columns and `source_upload_name`, `client_reference`, `expires_at`.
  - `airs.review_versions`: add `check_number` (smallint, CHECK in (1,2)), `document_set_hash` char(64), `score` smallint nullable, `shadow_outcome` string(20) nullable (`MEETS_THRESHOLD|BELOW_THRESHOLD`); unique `(review_case_id, check_number)`.
  - `airs.review_files`: add `source_document_id` (ULID FK `application_documents`, nullable for files extracted from a ZIP, restrictOnDelete).
  - Omit `review_finding_feedbacks` and `profile_field_reviews` (Plan 3), and every AIRS operational table.
- Produces relations: `ReviewCase::application(): BelongsTo`, `ReviewCase::versions(): HasMany`, `ReviewCase::latestVersion(): HasOne` (latest by `check_number`), `ReviewCase::activeFiles(): HasMany` (files where `submission_number = document_set_version`), `ReviewVersion::reviewCase()`, `findings()`, `aiRuns()`, `reports()`; `Application::airsCase(): HasOne` added to `app/Models/Application.php`.

- [ ] **Step 1: Write the failing test**

```php
public function test_airs_schema_tables_exist_and_provider_is_fake(): void
{
    foreach (['review_cases','review_versions','review_files','review_pages','page_regions','document_classifications',
              'application_profiles','review_rules','review_findings','review_evidence','ai_runs','processing_tasks',
              'generated_reports','system_config_snapshots'] as $table) {
        $this->assertTrue(Schema::hasTable("airs.$table"), $table);
    }
    $this->assertFalse(Schema::hasColumn('airs.review_cases', 'billing_required'));
    $this->assertInstanceOf(\App\Airs\Ai\FakeAiProvider::class, app(\App\Airs\Contracts\AiProvider::class));
}

public function test_version_check_number_is_limited_to_two(): void
{
    $case = /* create ReviewCase for an Application fixture */;
    $this->expectException(QueryException::class);
    ReviewVersion::create([... 'review_case_id' => $case->id, 'check_number' => 3 ...]);
}
```

Fixture helper `tests/Support/AirsFixtures.php` (trait) with `ljupApplication(): Application` — creates `ApplicationType` `LJUP` (department `JKEJ`, name "Pelan Lampu Jalan Untuk Pembangunan") if missing and an `Application` of that type; `ljupDocument(Application $a, string $fixturePath): ApplicationDocument` — stores bytes on `Storage::fake('local')` with correct size/sha256. Later tasks reuse this trait.

- [ ] **Step 2: Run** `php artisan test --filter=ModuleFoundationTest` — Expected: FAIL.
- [ ] **Step 3: Implement** config, provider, migration, enums, exceptions, models.
- [ ] **Step 4: Run** `php artisan test --filter="ModuleFoundationTest|DomainEnumTest"` — Expected: PASS (`FakeAiProvider` assertion passes once Task 5 lands; until then mark that single assertion `markTestIncomplete` and remove the marker in Task 5).
- [ ] **Step 5: Commit** — `git commit -m "AIRS module foundation: config, airs schema, models"`

---

### Task 4: Document preparation services and Python worker

**Files:**
- Create (ported): `app/Airs/DocumentProcessing/{ArchiveExtractor,FileValidator,DocumentInventoryService,DocumentRelevanceService,DocumentClassifier,PdfTextExtractor,PdfPageRenderer,PythonDocumentProcessor}.php`
- Create (copied verbatim): `python_worker/` from `AIRS:python_worker/` (exclude `__pycache__`); `python_worker/requirements.txt`
- Test (ported): `tests/Feature/Airs/{ArchiveExtractorTest,DocumentInventoryServiceTest,DocumentRelevanceServiceTest}.php`; copy any fixtures they use into `tests/Fixtures/airs/`

**Interfaces:**
- Consumes: Task 3 models and `config('airs.python')`, `config('airs.python_worker')`.
- Produces (signatures unchanged from AIRS apart from namespace): `ArchiveExtractor::extract(ReviewCase $case, ReviewFile $archive): array`; `DocumentInventoryService::inventory(ReviewCase $case): void`; `DocumentClassifier::classify(ReviewCase $case, ReviewVersion $version): void`; `DocumentRelevanceService::assertAtLeastOneRelevantDocument(ReviewCase $case, ReviewVersion $version): void`; `App\Airs\Contracts\DocumentProcessor::process(string $filePath, string $outputDirectory): array` bound to `PythonDocumentProcessor`.
- Storage root for derived files: `storage/app/airs/{case_id}/{check_number}/` (replace the AIRS `reviews/{case}` root).

- [ ] **Step 1: Port the three test classes**, replacing AIRS case factories with the `AirsFixtures` trait (a `ReviewCase` attached to `ljupApplication()`); keep every assertion (path traversal, decompression bomb, nested archive, encrypted PDF, duplicate detection, relevance failure).
- [ ] **Step 2: Run** `php artisan test --filter="ArchiveExtractorTest|DocumentInventoryServiceTest|DocumentRelevanceServiceTest"` — Expected: FAIL (classes missing).
- [ ] **Step 3: Port the services** applying the Global Constraints port rule.
- [ ] **Step 4: Verify the worker** — `python -m pytest python_worker/tests -q` → PASS; then the PHP tests above → PASS.
- [ ] **Step 5: Commit** — `git commit -m "AIRS module: document preparation services and Python worker"`

---

### Task 5: AI layer and MATRIX gate

**Files:**
- Create (ported): `app/Airs/Ai/{AiSchemas,AiResponseValidator,AiTaskRunner,FakeAiProvider,OpenAiProvider,MatrixClaudeProvider,MatrixReviewGate}.php`, `app/Airs/Console/MatrixStatus.php`, `app/Airs/Console/MatrixUsage.php`, `app/Airs/Review/MatrixUsageReport.php`
- Test (ported): `tests/Unit/Airs/AiResponseValidatorTest.php`, `tests/Feature/Airs/MatrixGateTest.php` (from the gate/budget cases in `AIRS:tests/Feature/MatrixIntegrationTest.php`; drop the profile-field-review cases, they move to Plan 3)

**Interfaces:**
- Consumes: Task 3 models/config.
- Produces: `AiTaskRunner::run(ReviewVersion $version, string $task, string $schemaId, array $input, callable $providerCall, callable $validator, ?string $ruleId = null): array` (unchanged); `MatrixReviewGate::context(ReviewVersion $version, string $requestId, bool $enforceBudget = true): array`.
- `MatrixReviewGate` rewiring (replaces the billing/integration branches): throw `MatrixTaskException` with
  - `MATRIX_SCOPE_DENIED` unless `airs.matrix.enabled`, `version.ai_provider === 'matrix'`, case module `STREET_LIGHTING`;
  - `MATRIX_STALE_REVIEW` unless `version` is the case's latest version;
  - `MATRIX_CLEARANCE_REQUIRED` unless the case has `clearance_kind`, `payment_clearance_reference` and `payment_cleared_at`;
  - budget checks unchanged.
  Returned context: `phase => 'FORMAL_REVIEW'`, `module`, `accepted => false` (acceptance now follows the final check), `payment_cleared => true`, `case_id`, `review_version_id`, `application_id`, `check_number`, `document_set_hash`, `request_id`, `declared_lighting_types`, `submission_round => check_number === 2 ? 'SECOND_OR_LATER' : 'FIRST'`.
- `MatrixClaudeProvider` reads `airs.matrix.node` and `airs.matrix.worker` (default `dirname(base_path()).'/MATRIX/runtime/cli.mjs'`); confirm the MATRIX runtime schema still accepts `accepted => false` by reading `../MATRIX/runtime/schema.mjs` — if it requires `accepted: true`, add a `MATRIX_CONTRACT_MISMATCH` note to the commit message and keep `accepted => true` with an extra `stage => 'PRE_SUBMISSION_PAID_CHECK'` field instead.
- Artisan commands keep their names: `matrix:status`, `matrix:usage`.

- [ ] **Step 1: Write failing tests**

```php
public function test_matrix_refused_without_payment_clearance(): void { /* case with null clearance → MATRIX_CLEARANCE_REQUIRED */ }
public function test_matrix_refused_for_non_street_lighting_module(): void { /* MATRIX_SCOPE_DENIED */ }
public function test_matrix_refused_for_superseded_check(): void { /* check 1 after check 2 exists → MATRIX_STALE_REVIEW */ }
public function test_matrix_budget_exceeded_is_refused(): void { /* seeded ai_runs estimated_cost summing to 4.90 → MATRIX_REVIEW_BUDGET */ }
public function test_context_reports_second_round_for_check_two(): void { /* submission_round === 'SECOND_OR_LATER' */ }
```

- [ ] **Step 2: Run** `php artisan test --filter="MatrixGateTest|AiResponseValidatorTest"` — Expected: FAIL.
- [ ] **Step 3: Port and rewire.** Remove the `markTestIncomplete` from Task 3's provider assertion.
- [ ] **Step 4: Run** the same filter plus `ModuleFoundationTest` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "AIRS module: AI layer, MATRIX gate bound to payment clearance"`

---

### Task 6: Checklist evaluation, scoring and report

**Files:**
- Create (ported): `app/Airs/Review/{RuleRepository,RuleApplicabilityService,RuleEvaluationService,EvidenceRetriever,FindingAggregator,FindingPolicy,ApplicationProfileExtractor,DeterministicProfileExtractor,MatrixProfileProjection,OfficerReferenceCalibrationService,ReadinessScorer,ReportGenerator,ReviewStateService}.php`
- Create (copied): `knowledge/ljup/` from `AIRS:knowledge/ljup/` (rules v1/v2, calibrations, prompts)
- Test (ported): `tests/Unit/Airs/{DeterministicProfileExtractorTest,FindingPolicyTest,RuleApplicabilityServiceTest}.php`, `tests/Feature/Airs/{FindingAggregatorTest,RuleRegistryGovernanceTest,LightingTypeApplicabilityFlowTest,OfficerReferenceCalibrationTest,ReviewStateServiceTest}.php`; new `tests/Feature/Airs/ScoringTest.php`

**Interfaces:**
- Consumes: Tasks 3–5.
- Produces (unchanged AIRS signatures, new namespace): `RuleRepository::syncPack(string $pack, string $version): Collection`; `ApplicationProfileExtractor::extract(ReviewCase $case, ReviewVersion $version): ApplicationProfile`; `RuleEvaluationService::evaluate(ReviewVersion $version, ApplicationProfile $profile, Collection $rules): void`; `FindingAggregator::aggregate(ReviewVersion $version): void`; `ReviewStateService::transition(ReviewCase $case, ReviewCaseStatus $to): void` and `fail(ReviewCase $case, string $code, string $message): void`; `ReportGenerator::generate(ReviewCase $case, ReviewVersion $version): GeneratedReport` (report_json only; drop HTML/PDF rendering and `dompdf` — screens arrive in Plans 2/3).
- New: `ReadinessScorer::shadowOutcome(int $score): string` returns `'BELOW_THRESHOLD'` when `$score < config('airs.score_threshold')`, else `'MEETS_THRESHOLD'`. `ReportGenerator::generate` writes `score` and `shadow_outcome` onto the version. Delete `isRejected()` and any "Ditolak" label.
- `RuleEvaluationService`: when the provider call for a rule throws `MatrixTaskException` with code `MATRIX_REVIEW_BUDGET`, `MATRIX_USAGE_RECONCILIATION_REQUIRED` or a provider timeout, record that rule's finding as `PERLU_SEMAKAN_MANUAL` with `manual_review_reason` "Tidak dapat dinilai dalam had kos/masa MATRIX" and continue; do not fail the check.

- [ ] **Step 1: Port the listed tests; write new tests**

```php
public function test_shadow_outcome_is_recorded_but_never_changes_application(): void
{
    config(['airs.score_threshold' => 101]); // force BELOW_THRESHOLD
    $version = /* run evaluation + report on fixture */;
    $this->assertSame('BELOW_THRESHOLD', $version->fresh()->shadow_outcome);
    $this->assertSame($stageBefore, $application->fresh()->current_stage);
    $this->assertSame($statusBefore, $application->fresh()->current_status);
}

public function test_budget_exhausted_rule_becomes_manual_review(): void
{
    // Fake provider whose evaluateRule throws new MatrixTaskException('MATRIX_REVIEW_BUDGET') for one AI rule
    // → that finding status === 'PERLU_SEMAKAN_MANUAL', other findings present, no exception escapes.
}

public function test_ditunaikan_requires_evidence(): void { /* FindingPolicy downgrades DITUNAIKAN without evidence */ }
```

- [ ] **Step 2: Run** `php artisan test --filter=Airs` — Expected: new tests FAIL.
- [ ] **Step 3: Port services, copy knowledge, add `shadowOutcome` and the budget handling.**
- [ ] **Step 4: Run** `php artisan test --filter=Airs` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "AIRS module: LJUP checklist evaluation, advisory score with shadow threshold"`

---

### Task 7: CheckService and the check pipeline job

**Files:**
- Create: `app/Airs/CheckClearance.php`, `app/Airs/CheckService.php`, `app/Airs/Exceptions/{CheckLimitReached,CheckInProgress,CheckNotAllowed}.php`, `app/Airs/Jobs/RunAirsCheck.php` (ported from `AIRS:app/Jobs/ProcessReviewCase.php`), `app/Airs/Console/RecoverAirsChecks.php`
- Modify: `routes/console.php` (schedule `airs:recover-checks` every minute)
- Test: `tests/Feature/Airs/CheckServiceTest.php`, `tests/Feature/Airs/RunAirsCheckTest.php` (ported from `AIRS:tests/Feature/ProcessReviewCaseTest.php`)

**Interfaces:**
- Consumes: everything above.
- Produces:
  - `final readonly class CheckClearance { public function __construct(public string $kind /* 'paid'|'waived' */, public string $reference, public CarbonImmutable $clearedAt) }` — constructor throws `InvalidArgumentException` for other kinds or blank reference.
  - `CheckService::start(Application $application, CheckClearance $clearance, array $lightingTypes, Collection $documents /* of ApplicationDocument */): ReviewVersion`
  - `CheckService::rerun(ReviewVersion $failed): ReviewVersion` — staff/system rerun of a `FAILED` check.
  - `CheckService::completedChecks(Application $application): int`
  - Job `RunAirsCheck(string $reviewVersionId)` on queue `config('airs.queue')`, `tries=3`, `timeout=900`, backoff `[30,120,300]`, `WithoutOverlapping('airs-check-'.$caseId)`.
- `start` behaviour, in a DB transaction holding `pg_advisory_xact_lock(hashtext('airs:'.$application->id))`:
  1. Module = `config('airs.modules')[$application->application_type_id]`; missing or not `enabled` → `CheckNotAllowed('MODULE_NOT_ENABLED')`.
  2. Each lighting type must be a key of `airs.lighting_types` and at least one given → else `CheckNotAllowed('LIGHTING_TYPES_REQUIRED')`.
  3. Any version of this case in a non-terminal status (not `COMPLETED`/`FAILED`) → `CheckInProgress`.
  4. `completedChecks >= 2` → `CheckLimitReached`.
  5. Each document: `is_current`, `scan_status === 'clean'`, belongs to `$application`, stored bytes' sha256 equals `checksum_sha256` → else `CheckNotAllowed('DOCUMENT_INTEGRITY')`.
  6. Create/update the case (clearance fields, lighting types), set `document_set_version = check_number = completedChecks + 1`, copy each document's bytes to `airs/{case}/{check}/originals/{document_id}.{ext}` as `ReviewFile` rows (`submission_number = check_number`, keep `original_name`, `mime_type`, `size_bytes`, set `source_document_id`), compute `document_set_hash` via `DocumentManifest::hash`, create `ReviewVersion` (`check_number`, `rule_pack`, `rule_version`, `prompt_version`, `report_version`, `ai_provider = config('airs.provider')`, models), dispatch `RunAirsCheck` after commit.
- `rerun($failed)`: only when `$failed->status === 'FAILED'`; resets the same version to `UPLOADED`, clears its tasks' failure state, re-dispatches; never increments `check_number`.
- `RunAirsCheck::handle` = AIRS pipeline steps in the same order and task names (`EXTRACT_ARCHIVE` … `GENERATE_REPORT`), with all billing/submission guards removed; exits early if the version is not the case's latest or already `COMPLETED`.
- `airs:recover-checks`: re-dispatches versions in `UPLOADED` older than 2 minutes with no `processing_tasks` rows.

- [ ] **Step 1: Write failing tests** (`CheckServiceTest`, `Queue::fake()` unless stated)

```php
test_first_start_creates_check_one_and_dispatches_job()            // check_number 1, RunAirsCheck pushed on 'airs'
test_refuses_application_type_without_enabled_module()             // PBK application → CheckNotAllowed MODULE_NOT_ENABLED
test_refuses_missing_lighting_types()                               // [] → LIGHTING_TYPES_REQUIRED
test_refuses_document_whose_bytes_changed()                         // overwrite stored file → DOCUMENT_INTEGRITY
test_refuses_second_start_while_check_in_progress()                 // second start → CheckInProgress; versions count 1
test_second_start_after_completion_creates_check_two()              // mark v1 COMPLETED → check_number 2, files submission_number 2
test_third_check_is_refused()                                       // two COMPLETED → CheckLimitReached
test_failed_check_is_rerun_without_consuming_a_check()              // v1 FAILED → rerun → same id, check_number 1; completedChecks 0
test_clearance_rejects_unknown_kind()                               // new CheckClearance('free', ...) throws
```

`RunAirsCheckTest` (sync queue, fake provider, Green-Camour-like small fixture PDFs from `tests/Fixtures/airs/`): ported assertions from `ProcessReviewCaseTest` — state progression ends `COMPLETED`, every task row `COMPLETED`, findings exist, report exists with `score` and `shadow_outcome`, invalid AI output marks run `FAILED` without corrupting findings, `application.current_status` unchanged.

- [ ] **Step 2: Run** `php artisan test --filter="CheckServiceTest|RunAirsCheckTest"` — Expected: FAIL.
- [ ] **Step 3: Implement** `CheckClearance`, `CheckService`, exceptions, job, recovery command, schedule entry.
- [ ] **Step 4: Run** `php artisan test --filter=Airs` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "AIRS module: CheckService with two-check cap and pipeline job"`

---

### Task 8: Green Camour acceptance and full-suite verification

**Files:**
- Test (ported): `tests/Feature/Airs/GreenCamourAcceptanceTest.php` from `AIRS:tests/Feature/GreenCamourAcceptanceTest.php`, driven through `CheckService::start` with a test `CheckClearance('paid', 'TEST-…', now())` and 12 `ApplicationDocument`s built from the files at `env('PLAN_REVIEW_GREEN_CAMOUR_PATH')` (skipped when unset, as in AIRS)
- Modify: `README.md` (AIRS module section: Python requirements, `AIRS_*` env keys, `airs` queue worker, `airs:recover-checks`, `matrix:status`), `AGENTS.md` (one paragraph: AIRS engine now lives in `app/Airs`; Plans 2–3 pending)

**Interfaces:** Consumes Task 7 only.

- [ ] **Step 1: Port the test.** Keep the AGENTS.md acceptance assertions: 12 PDFs, 79 pages, document types classified, street-lighting plan linked to photometric report, 23 × 150 W and 7 × 180 W where readable, payment document described as registration/demand, site photographs not marked present from checklist mentions.
- [ ] **Step 2: Run** `PLAN_REVIEW_GREEN_CAMOUR_PATH="C:/Users/Asyra/Documents/Plan Review/test_subject" php artisan test --filter=GreenCamourAcceptanceTest` — Expected: PASS.
- [ ] **Step 3: Run the full suite** `php artisan test` — Expected: all PASS (pre-existing iNDECS tests unchanged).
- [ ] **Step 4: Run** `grep -rn "BillingService\|SubmissionStatus\|plan_review\.\|config('matrix" app/Airs` — Expected: no output.
- [ ] **Step 5: Commit** — `git commit -m "AIRS module: Green Camour acceptance; docs"`
