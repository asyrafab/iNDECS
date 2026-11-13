# AIRS Applicant Check Journey (Plan 2 of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a PSP take an LJUP (street-lighting) draft from the free precheck through payment, AIRS check 1, one optional amendment with check 2, and automatic handover to Jabatan Kejuruteraan (JKEJ), seeing an applicant-safe result after each check.

**Architecture:** iNDECS core owns the journey. A paid-check journey record (`core.paid_check_journeys`) ties one `ApplicantDraft` to its payment, the `Application` created at payment, and the AIRS checks. Payment goes through a `PaymentGateway` interface; only a staging driver is built (refused in production) because no MBSP gateway API is available yet. Checks run through Plan 1's `App\Airs\CheckService`; a new `AirsCheckCompleted` event moves the journey forward. Handover writes the immutable `core.accepted_submissions` record. A working-day calendar (`core.public_holidays`) drives the 5 + 5 working-day amendment window.

**Tech Stack:** Laravel 13.8, PHP 8.3, PostgreSQL 16 (`indecs_test`), Blade + Tailwind portal views, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-11-13-airs-module-merge-design.md` (§5.1–§5.6, §9). Builds on Plan 1: `docs/superpowers/plans/2026-11-13-airs-engine-module.md`.

**Not in this plan:** the real payment gateway (needs MBSP gateway API details), officer waiver screen, officer workspace and per-finding comments, MATRIX knowledge retention, ADR and documentation rewrite (Plan 3), applicant e-mail notifications.

## Global Constraints

- Free precheck stays free and local: no MATRIX/OpenAI call and no score before payment. Existing precheck labels unchanged (`Dilampirkan`, `Belum dilampirkan`, `Tidak dapat dibaca`, `Perlu pengesahan pemohon`, `Sedang disemak`).
- One fee covers at most **two** completed checks per application; failed checks never count (enforced by `CheckService`).
- Amendment: **one** per application, within **5 working days** of check 1 completing, extendable **once** by **5 working days** by the applicant. Working days exclude Saturday, Sunday and dates in `core.public_holidays`.
- Applicant submits without amending → **check 1 is final**, no check 2. Window ends with no amendment → check 1 final, automatic handover.
- After check 2 the result is **read-only** and the application hands over automatically.
- AIRS never closes or rejects an application. Missing required documents are shown to the applicant and, at handover, flagged for the officer as `Dokumen wajib tidak dilampirkan`.
- Applicant view shows: rule title, status, standard comment, evidence document + page link, overall score. `PERLU_SEMAKAN_MANUAL` and `PERLU_SEMAKAN_MANUAL_PEGAWAI` display as `Akan disemak oleh pegawai`. Never shown to applicants: AI rationale (`finding` text), confidence, `shadow_outcome`, officer comments, raw extracted values.
- Payment confirmation is idempotent (by gateway event id) and never trusted from a browser redirect alone. The staging gateway refuses when `app()->isProduction()`.
- Fee amount comes only from `AIRS_CHECK_FEE_SEN`; when unset the pay button is disabled with `Fi semakan belum ditetapkan`.
- Every state change writes an `AuditLog` row (`auditable_type`, `auditable_id`, `event`, `actor_id`, `before_values`, `after_values`, `ip_address`).
- Tests use the fake AI provider, `AIRS_QUEUE_DRIVER=sync`, `Storage::fake('local')`, `indecs_test`.

## Review Focus

1. A double-submitted payment confirmation (same gateway event twice, or two different events for one draft) — must create exactly one application and one check (Task 4 test `duplicate_confirmation_creates_one_application_and_one_check`).
2. Applicant edits a document after paying but outside the amendment window, or a second time — must be refused (Task 6 tests `draft_is_locked_while_check_runs`, `second_amendment_is_refused`).
3. Deadline passes while the applicant has the amendment form open — submission after the deadline must be refused and the handover must already have happened or happen once (Task 7 test `amend_after_deadline_is_refused`).
4. Another user reads a draft's check result or downloads an evidence document — must 404 (Task 5 test `other_users_cannot_see_result_or_documents`).
5. Check 1 fails permanently (e.g. no relevant documents) — the journey must not hand over and must not start a deadline; the applicant sees a staff-contact message (Task 5 test `failed_check_shows_contact_message_and_no_deadline`).

---

### Task 1: LJUP application type, JKEJ roles, LJUP catalogue publishing, lighting types on drafts

**Files:**
- Create: `database/migrations/2026_11_15_000100_add_ljup_application_type.php`, `app/Services/DocumentCatalogueService.php`, `app/Console/Commands/PublishDocumentCatalogue.php`
- Modify: `database/seeders/DatabaseSeeder.php` (LJUP type, JKEJ roles/permissions), `app/Models/ApplicationType.php` (`PORTAL_TYPES` gains `LJUP`), `app/Services/BangunanDocumentCatalogueService.php` (delegate to the new service), `app/Models/ApplicantDraft.php`, `app/Http/Controllers/PublicPortalController.php` (`validated()`), `resources/views/public/drafts/edit.blade.php` (lighting-type checkboxes when type is LJUP)
- Test: `tests/Feature/PaidCheck/LjupSetupTest.php`

**Interfaces:**
- Produces: application type `LJUP` (`name` "Pelan Lampu Jalan Untuk Pembangunan", `primary_department_id` `JKEJ`, active). Migration inserts it with `insertOrIgnore` so existing environments get it; seeder does the same.
- Produces roles `engineering_officer`, `senior_engineering_officer` and permissions `kejuruteraan.ljup.access`, `kejuruteraan.ljup.review`, `kejuruteraan.ljup.documents.configure`, granted in `core.role_permissions` with `department_id` `JKEJ`, `application_type_id` `LJUP` (senior gets `documents.configure`).
- Produces `DocumentCatalogueService::publish(string $type, array $data, User $actor): void` — the body of today's `BangunanDocumentCatalogueService::publish` with three things looked up from a scope table instead of hard-coded: allowed types, owning department (`JBANG` for Bangunan types, `JKEJ` for `LJUP`), document-type code prefix (`bina_` / `kej_`) and the permission check (`canPerformBangunan('documents.configure', $type)` / `userCan($actor, 'kejuruteraan.ljup.documents.configure', 'JKEJ', 'LJUP')`). `BangunanDocumentCatalogueService::publish` becomes a one-line delegate (keep its static `keywords()`).
- Produces Artisan `catalogue:publish {type} {file} {--actor=}`: reads a JSON file shaped like the publish payload (`version`, `source_reference`, `items[]`), resolves `--actor` by e-mail, calls the service. JKEJ supplies the LJUP list; nothing is seeded by the application.
- Produces `core.applicant_drafts.lighting_types` (jsonb, nullable) and `ApplicantDraft::lightingTypes(): list<string>`; `validated()` accepts `lighting_types` as an array of keys of `config('airs.lighting_types')`, required (min 1) when `application_type_id` is `LJUP`, ignored otherwise.

- [ ] **Step 1: Write the failing tests** (`LjupSetupTest`)

```php
test_ljup_is_a_portal_type_owned_by_jkej()              // ApplicationType::portal()->pluck('id') contains 'LJUP'; primary_department_id 'JKEJ'
test_senior_engineer_publishes_ljup_catalogue()         // catalogue:publish LJUP fixture.json --actor=senior → core.document_catalogues version 1, document types prefixed 'kej_ljup_'
test_building_officer_cannot_publish_ljup_catalogue()   // DocumentCatalogueService::publish('LJUP', ..., $buildingOfficer) → HttpException 403
test_bangunan_catalogue_publishing_unchanged()          // existing Bangunan publish path still versions and prefixes 'bina_'
test_ljup_draft_requires_lighting_types()               // POST portal.drafts.store type LJUP without lighting_types → session error 'lighting_types'
test_ljup_draft_stores_lighting_types()                 // with ['BESI','SOLAR'] → draft->lightingTypes() === ['BESI','SOLAR']
test_non_ljup_draft_ignores_lighting_types()            // PKM draft with lighting_types → stored null
```

- [ ] **Step 2: Run** `php artisan test --filter=LjupSetupTest` — Expected: FAIL.
- [ ] **Step 3: Implement** migration, seeder, `PORTAL_TYPES`, `DocumentCatalogueService`, delegate, command, draft field, validation, view checkboxes (labels from `config('airs.lighting_types.*.label')`).
- [ ] **Step 4: Run** `php artisan test --filter="LjupSetupTest|Bangunan|Portal|Precheck"` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "LJUP application type under JKEJ, catalogue publishing and lighting types"`

---

### Task 2: Working-day calendar

**Files:**
- Create: `database/migrations/2026_11_15_000200_create_public_holidays.php`, `app/Models/PublicHoliday.php`, `app/Services/WorkingCalendar.php`
- Test: `tests/Unit/WorkingCalendarTest.php` (uses RefreshDatabase; extends `Tests\TestCase`)

**Interfaces:**
- Produces table `core.public_holidays` (`date` date primary key, `name` string, timestamps). Empty by default; maintained by admins later.
- Produces `WorkingCalendar::addWorkingDays(CarbonImmutable $from, int $days): CarbonImmutable` — returns the end of the Nth working day after `$from` (23:59:59 Asia/Kuala_Lumpur); `$from`'s own day never counts. `WorkingCalendar::isWorkingDay(CarbonImmutable $day): bool`.

- [ ] **Step 1: Write the failing tests**

```php
test_counts_five_working_days_skipping_weekend()   // from Fri 2026-11-13 10:00 → Fri 2026-11-20 23:59:59
test_skips_public_holidays()                        // holiday 2026-11-17 → Mon 2026-11-23 23:59:59
test_start_day_is_not_counted_even_if_working()     // from Mon 2026-11-16 08:00, 1 day → Tue 2026-11-17 23:59:59
```

- [ ] **Step 2: Run** `php artisan test --filter=WorkingCalendarTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** same filter — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Working-day calendar with public holidays"`

---

### Task 3: Check payments with a staging gateway

**Files:**
- Create: `database/migrations/2026_11_15_000300_create_check_payments.php`, `app/Models/CheckPayment.php`, `app/Services/PaidCheck/PaymentGateway.php` (interface), `app/Services/PaidCheck/StagingPaymentGateway.php`, `app/Services/PaidCheck/CheckPaymentService.php`, `app/Http/Controllers/PortalCheckPaymentController.php`
- Modify: `config/airs.php` (add `fee_sen` = `env('AIRS_CHECK_FEE_SEN')` cast to int or null, `payment_driver` = `env('AIRS_PAYMENT_DRIVER', 'staging')`), `routes/public.php`, `resources/views/public/drafts/partials/checklist.blade.php` (replace the disabled button), `app/Providers/AppServiceProvider.php` (bind `PaymentGateway`), `.env.example`
- Test: `tests/Feature/PaidCheck/CheckPaymentTest.php`

**Interfaces:**
- Produces table `core.check_payments`: `id` ulid, `draft_id` (FK `core.applicant_drafts`), `user_id`, `amount_sen` int, `reference` string unique (`PAY-LJUP-` + ULID), `status` (`pending|paid|cancelled`), `gateway` string, `gateway_event_id` string nullable unique, `paid_at` timestampTz nullable, timestamps. Partial unique index: one `paid` payment per draft.
- Produces `interface PaymentGateway { public function checkoutUrl(CheckPayment $payment): string; public function name(): string; }`.
- Produces `CheckPaymentService::start(User $owner, ApplicantDraft $draft, bool $acknowledgedMissing): CheckPayment` — requires: owner owns draft, draft type has an enabled AIRS module (`config('airs.modules')`), at least one draft document, no item labelled `Sedang disemak` or `Tidak dapat dibaca`, `config('airs.fee_sen')` set, no existing paid payment. Missing required documents do **not** block payment (exemptions exist, spec §5.3) but require `$acknowledgedMissing === true` (checkbox `Saya faham dokumen wajib belum lengkap dan pegawai akan menilainya`). Reuses an existing `pending` payment for the draft.
- Produces `CheckPaymentService::confirm(string $reference, string $gatewayEventId, CarbonImmutable $paidAt): CheckPayment` — in a transaction with `lockForUpdate`; same event id again → returns the payment unchanged; already `paid` with a different event id → returns unchanged and logs `check_payment_duplicate_event`; otherwise marks `paid`, writes AuditLog `check_payment_confirmed`, then dispatches event `App\Events\CheckPaymentConfirmed(string $paymentId)` after commit (Task 4 listens).
- Staging gateway: `checkoutUrl` → `route('portal.drafts.payment.staging', $payment)`; that page has a "Sahkan bayaran ujian" button posting to `portal.drafts.payment.staging.confirm`, which calls `confirm($payment->reference, 'staging-'.$payment->id, now())`. Both routes `abort(404)` when `app()->isProduction()` or driver ≠ `staging`, and require the payment's owner.
- Routes (inside `public.access`): `POST /portal/drafts/{draft}/payment` (`portal.drafts.payment.start`, throttle 10/min) → redirect to `checkoutUrl`; `GET /portal/payments/{payment}/staging` and `POST /portal/payments/{payment}/staging/confirm`.
- Checklist partial: fee unset → disabled `Fi semakan belum ditetapkan`; fee set → form button `Bayar semakan AIRS (RM x.xx)`, and when required documents are missing the heading stays `Lengkapkan dokumen wajib untuk meneruskan` with the acknowledgement checkbox above the button. Keep the existing assertions in `PortalPrecheckPagesTest` passing (`Bayar semakan AIRS`, `Lengkapkan dokumen wajib untuk meneruskan`).

- [ ] **Step 1: Write the failing tests**

```php
test_pay_button_disabled_when_fee_not_set()                 // config airs.fee_sen null → see 'Fi semakan belum ditetapkan'
test_payment_with_missing_required_needs_acknowledgement()  // required missing, no checkbox → validation error, no row; with checkbox → pending row
test_payment_refused_while_a_document_is_unreadable_or_pending() // label Tidak dapat dibaca / Sedang disemak → validation error
test_owner_starts_payment_and_is_sent_to_staging_checkout() // fee 15000 → row pending amount 15000, redirect to staging page
test_confirm_dispatches_payment_confirmed_event()          // Event::fake → CheckPaymentConfirmed dispatched once
test_other_user_cannot_start_or_confirm_payment()           // 404
test_confirm_is_idempotent_for_same_event()                 // confirm twice same event → one paid row, one AuditLog 'check_payment_confirmed'
test_staging_gateway_refused_in_production()                // app()->detectEnvironment(fn () => 'production') → staging routes 404
test_non_ljup_draft_cannot_pay()                            // PKM draft → refused (no enabled AIRS module)
```

- [ ] **Step 2: Run** `php artisan test --filter=CheckPaymentTest` — Expected: FAIL.
- [ ] **Step 3: Implement** (create `app/Events/CheckPaymentConfirmed.php`; no listener yet).
- [ ] **Step 4: Run** `php artisan test --filter="CheckPaymentTest|PortalPrecheckPagesTest"` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Check payments with staging gateway (refused in production)"`

---

### Task 4: Paid-check journey — application created at payment, check 1 started

**Files:**
- Create: `database/migrations/2026_11_15_000400_create_paid_check_journeys.php`, `app/Models/PaidCheckJourney.php`, `app/Enums/PaidCheckState.php`, `app/Services/PaidCheck/PaidCheckJourneyService.php`, `app/Airs/Events/AirsCheckCompleted.php`
- Modify: `app/Enums/ApplicationStage.php` (add `TechnicalCheck = 'technical_check'` with label `Semakan Teknikal AIRS` wherever labels are mapped), `app/Airs/Jobs/RunAirsCheck.php` (dispatch `AirsCheckCompleted` after the version is `COMPLETED`), `app/Models/ApplicantDraft.php` (`journey(): HasOne`)
- Test: `tests/Feature/PaidCheck/PaidCheckJourneyTest.php`, add one assertion to `tests/Feature/Airs/RunAirsCheckTest.php` (`Event::assertDispatched(AirsCheckCompleted::class)`)

**Interfaces:**
- Produces enum `PaidCheckState`: `CHECK_1_RUNNING`, `AWAITING_APPLICANT`, `CHECK_2_RUNNING`, `HANDED_OVER`.
- Produces table `core.paid_check_journeys`: `id` ulid, `draft_id` unique FK, `application_id` unique FK `applications`, `payment_id` unique FK `core.check_payments`, `state` string, `lighting_types` jsonb, `amendment_due_at` timestampTz nullable, `extended_at` timestampTz nullable, `amended_at` timestampTz nullable, `final_check_number` smallint nullable, `handed_over_at` timestampTz nullable, timestamps.
- Produces `final readonly class AirsCheckCompleted { public function __construct(public string $reviewVersionId, public string $applicationId, public int $checkNumber) {} }` (in `App\Airs\Events`).
- Registers listeners (in `AppServiceProvider::boot` via `Event::listen`): `CheckPaymentConfirmed` → `paid(CheckPayment::findOrFail($event->paymentId))`; `AirsCheckCompleted` → `checkCompleted($event)`. Listeners run synchronously (not queued) so state changes stay in order.
- Produces `PaidCheckJourneyService::paid(CheckPayment $payment): PaidCheckJourney` — idempotent (existing journey for the payment's draft → return it). In one transaction:
  1. Create `Application`: type from draft, `application_reference` from `ApplicationReferenceGenerator::next($type, now())`, `title` = draft title, `description` = draft site address, `submission_date` today, `responsible_department_id` = the type's `primary_department_id`, `current_stage` `TechnicalCheck`, `current_status` `InReview`.
  2. Copy each latest draft document that belongs to a checklist item that currently applies into `application_documents` (`storage_path` `applications/{app}/{ulid}.{ext}`, bytes copied, same `checksum_sha256`, `size_bytes`, `document_type_id`, `original_name`, `mime_type`, `scan_status` `clean`, `scanned_at` now, `uploaded_by` draft owner, `revision_number` 1, `is_current` true, `verification_status` pending).
  3. Create the journey (`CHECK_1_RUNNING`, lighting types from `$draft->lightingTypes()`), AuditLog `paid_check_started`.
  After commit: `CheckService::start($application, new CheckClearance('paid', $payment->reference, $payment->paid_at), $journey->lighting_types, $application->currentDocuments()->get())`.
- Produces `PaidCheckJourneyService::checkCompleted(AirsCheckCompleted $event): void`, registered as the event listener:
  - check 1 completed and state `CHECK_1_RUNNING` → state `AWAITING_APPLICANT`, `amendment_due_at` = `WorkingCalendar::addWorkingDays(now, 5)`.
  - check 2 completed and state `CHECK_2_RUNNING` → `handOver($journey, 2)` (Task 7).
  - any other combination → ignore (idempotent).

- [ ] **Step 1: Write the failing tests**

```php
test_payment_creates_jkej_application_with_copied_documents()   // stage technical_check, dept JKEJ, ref 'LJUP/2026/00001', N application_documents with matching sha256
test_check_one_runs_and_journey_awaits_applicant()               // sync queue + FakeAirsDocumentProcessor → journey AWAITING_APPLICANT, amendment_due_at = calendar(+5)
test_duplicate_confirmation_creates_one_application_and_one_check() // confirm same payment twice and call paid() twice → 1 application, 1 review version
test_documents_for_items_that_no_longer_apply_are_not_copied()   // upload for a conditional item, change particulars → not copied
test_check_completed_event_is_dispatched()                       // in RunAirsCheckTest: Event::fake([AirsCheckCompleted::class]); assertDispatched with checkNumber 1
```

- [ ] **Step 2: Run** `php artisan test --filter="PaidCheckJourneyTest|RunAirsCheckTest"` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="PaidCheck|Airs"` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Paid-check journey: application at payment, check 1, completion event"`

---

### Task 5: Applicant result view

**Files:**
- Create: `app/Services/PaidCheck/ApplicantCheckPresenter.php`, `app/Http/Controllers/PortalCheckResultController.php`, `resources/views/public/drafts/partials/check-result.blade.php`
- Modify: `routes/public.php`, `resources/views/public/drafts/edit.blade.php` (include the result partial when a journey exists; hide the pay button), `app/Services/Portal/PortalBoard.php` (card status text per journey state)
- Test: `tests/Feature/PaidCheck/ApplicantCheckResultTest.php`

**Interfaces:**
- Produces `ApplicantCheckPresenter::for(PaidCheckJourney $journey): array` returning
  `['state' => PaidCheckState, 'checks' => list<['number' => int, 'status' => 'running'|'completed'|'failed', 'score' => ?int, 'items' => list<['title' => string, 'status_label' => string, 'standard_comment' => ?string, 'evidence' => list<['document' => string, 'page' => ?int, 'url' => string]>]>, 'missing_required' => list<string>]>, 'amendment_due_at' => ?CarbonImmutable, 'can_extend' => bool, 'can_amend' => bool, 'can_submit' => bool]`.
  Items come from the version's latest `GeneratedReport.report_json` sections (`title`, `status`, `standard_comment`, `evidence[*].source_document_id/page`). Status labels: `DITUNAIKAN` → `Dipenuhi`, `BELUM_DITUNAIKAN` → `Belum dipenuhi`, `TIDAK_BERKAITAN` → `Tidak berkaitan`, `TIDAK_DAPAT_DIKESAN` → `Tidak dikesan`, both manual statuses → `Akan disemak oleh pegawai`. The presenter must not copy `finding`, `confidence`, `extracted_values`, `manual_review_reason`, `warnings` or `shadow_outcome`.
  `missing_required` = names of required checklist items with no copied application document for that check.
  `failed` check: case `error_code` = `MATRIX_UNAVAILABLE` → message `Semakan sedang diulang semula secara automatik`; other codes → `Semakan tidak dapat diselesaikan. Pegawai JKEJ akan menghubungi anda.`
- Routes: `GET /portal/drafts/{draft}/check` (`portal.drafts.check`, polled every 10 s by the partial while running) and `GET /portal/drafts/{draft}/check/documents/{document}` (`portal.drafts.check.document`) streaming an `ApplicationDocument` of the journey's application, owner only, appending nothing (the page link uses `#page=N` client-side).

- [ ] **Step 1: Write the failing tests**

```php
test_running_check_shows_progress_and_no_score()
test_completed_check_shows_titles_labels_comments_and_score()      // assertSee 'Akan disemak oleh pegawai'
test_result_hides_ai_rationale_confidence_and_shadow_outcome()      // assertDontSee a finding's `finding` text, 'MEETS_THRESHOLD', 'BELOW_THRESHOLD', 'confidence'
test_missing_required_document_is_listed()                          // required item without document → see its name under 'Dokumen wajib belum dilampirkan'
test_evidence_link_streams_own_document()                           // 200 with matching bytes
test_other_users_cannot_see_result_or_documents()                   // 404 both routes
test_failed_check_shows_contact_message_and_no_deadline()           // version FAILED, error NO_RELEVANT_DOCUMENTS → contact message, amendment_due_at null, no amend button
```

- [ ] **Step 2: Run** `php artisan test --filter=ApplicantCheckResultTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** same filter plus `PortalPrecheckPagesTest|PublicPortalTest` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Applicant-safe AIRS check result on the portal"`

---

### Task 6: Draft locking, extension and the single amendment (check 2)

**Files:**
- Modify: `app/Services/PaidCheck/PaidCheckJourneyService.php`, `app/Services/Precheck/DraftDocumentService.php` (`authorize()` also requires `PaidCheckJourneyService::draftEditable($draft)`), `app/Http/Controllers/PublicPortalController.php` (`update()` same guard), `app/Http/Controllers/PortalCheckResultController.php`, `routes/public.php`, `resources/views/public/drafts/partials/check-result.blade.php`
- Test: `tests/Feature/PaidCheck/AmendmentTest.php`

**Interfaces:**
- Produces `PaidCheckJourneyService::draftEditable(ApplicantDraft $draft): bool` — true when there is no journey, or state `AWAITING_APPLICANT`, `amended_at` null and `now() <= amendment_due_at`.
- Produces `PaidCheckJourneyService::extend(User $owner, PaidCheckJourney $journey): void` — owner only; state `AWAITING_APPLICANT`; `extended_at` null; before deadline → `amendment_due_at` = `WorkingCalendar::addWorkingDays(amendment_due_at, 5)`, `extended_at` now, AuditLog `amendment_window_extended`. Otherwise `ValidationException` (`Tempoh pindaan hanya boleh dilanjutkan sekali sebelum tamat.`).
- Produces `PaidCheckJourneyService::submitAmendment(User $owner, PaidCheckJourney $journey): void` — owner only, under `lockForUpdate` on the journey; requires `draftEditable`; recomputes the draft checklist; for each applicable item's latest draft document: unchanged sha256 → keep; changed → new `ApplicationDocument` revision (`supersedes_id` = current, old `is_current` false); item with an application document but no longer applicable or removed → old `is_current` false; new item → new document. Updates `journey.lighting_types` from the draft. Sets `amended_at`, state `CHECK_2_RUNNING`, AuditLog `amendment_submitted`; after commit `CheckService::start(... check 2 ...)` with the same clearance.
- Produces `PaidCheckJourneyService::submitAsIs(User $owner, PaidCheckJourney $journey): void` — owner, state `AWAITING_APPLICANT`, `amended_at` null → `handOver($journey, 1)`.
- Routes: `POST /portal/drafts/{draft}/check/extend`, `POST /portal/drafts/{draft}/check/amend`, `POST /portal/drafts/{draft}/check/submit` (throttle 10/min each).

- [ ] **Step 1: Write the failing tests**

```php
test_draft_is_locked_while_check_runs()            // upload during CHECK_1_RUNNING → refused (403/validation), no new draft document
test_draft_unlocks_during_amendment_window()
test_extension_adds_five_working_days_once()       // second extend → validation error
test_amendment_creates_revisions_and_runs_check_two() // changed file → new revision supersedes old; check_number 2 runs; state CHECK_2_RUNNING then handed over (sync)
test_second_amendment_is_refused()
test_amendment_can_change_lighting_types()          // journey.lighting_types updated; review case selected_lighting_types updated
test_submit_as_is_hands_over_with_check_one_final() // final_check_number 1, no check 2 version
```

- [ ] **Step 2: Run** `php artisan test --filter=AmendmentTest` — Expected: FAIL.
- [ ] **Step 3: Implement** (handover body comes in Task 7; until then `handOver()` sets state `HANDED_OVER`, `final_check_number`, `handed_over_at` only).
- [ ] **Step 4: Run** `php artisan test --filter="AmendmentTest|PaidCheck|Precheck|Portal"` — Expected: PASS.
- [ ] **Step 5: Commit** — `git commit -m "Amendment window: locking, one extension, single amendment with check 2"`

---

### Task 7: Handover to JKEJ and automatic expiry

**Files:**
- Create: `database/migrations/2026_11_15_000500_add_missing_required_to_accepted_submissions.php`, `app/Console/Commands/HandOverExpiredChecks.php`
- Modify: `app/Services/PaidCheck/PaidCheckJourneyService.php` (`handOver`), `app/Models/AcceptedSubmission.php`, `routes/console.php` (schedule `paid-check:hand-over-expired` every five minutes, `withoutOverlapping`)
- Test: `tests/Feature/PaidCheck/HandOverTest.php`

**Interfaces:**
- Produces column `core.accepted_submissions.missing_required_documents` jsonb not null default `'[]'` (added before any row exists; the table's immutability triggers stay).
- Produces `PaidCheckJourneyService::handOver(PaidCheckJourney $journey, int $finalCheckNumber): void` — under journey `lockForUpdate`; no-op when already `HANDED_OVER`. Writes `AcceptedSubmission` with `document_manifest` = the final check's `system_config_snapshots.config_json.document_manifest`, `document_set_hash` = that version's `document_set_hash`, `clearance_kind` `paid`, `payment_clearance_reference` = payment reference, `payment_cleared_at` = `paid_at`, `accepted_at` now, `missing_required_documents` = presenter's `missing_required` for that check. Application → stage `TechnicalCirculation`, status `Submitted`. Journey → `HANDED_OVER`, `final_check_number`, `handed_over_at`. AuditLog `paid_check_handed_over` with `missing_required_documents`.
- Produces Artisan `paid-check:hand-over-expired`: journeys in `AWAITING_APPLICANT` with `amended_at` null and `amendment_due_at < now()` → `handOver($journey, 1)`.
- `submitAmendment`/`extend` after the deadline → `ValidationException` `Tempoh pindaan telah tamat.`

- [ ] **Step 1: Write the failing tests**

```php
test_handover_writes_accepted_submission_and_moves_application()   // stage technical_circulation, status submitted, hash = version hash
test_handover_flags_missing_required_documents()                    // 'Dokumen wajib tidak dilampirkan' names stored; application still accepted
test_handover_is_idempotent()                                        // call twice → one accepted_submissions row
test_expired_window_hands_over_check_one()                           // travel past due → command → HANDED_OVER, final_check_number 1
test_amend_after_deadline_is_refused()
test_check_two_completion_hands_over_automatically()
test_handover_never_rejects_or_closes_application()                 // status never 'rejected'/'closed' even with low score and missing docs
```

- [ ] **Step 2: Run** `php artisan test --filter=HandOverTest` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test` — Expected: full suite PASS.
- [ ] **Step 5: Commit** — `git commit -m "Hand over to JKEJ with missing-document flag; automatic expiry"`

---

### Task 8: End-to-end journey test and docs

**Files:**
- Create: `tests/Feature/PaidCheck/LjupJourneyEndToEndTest.php`
- Modify: `README.md` (AIRS module section: payment staging driver, `AIRS_CHECK_FEE_SEN`, `catalogue:publish`, scheduler entries, holidays table), `AGENTS.md` (one line: Plan 2 delivered; real gateway and waiver pending)

**Interfaces:** consumes all tasks.

- [ ] **Step 1: Write the test** — one PSP: create LJUP draft with `['BESI']`, publish a fixture LJUP catalogue (two required items), upload both, confirm, pay via staging, check 1 completes (sync, fake provider, `FakeAirsDocumentProcessor`), result page shows score and labels, amend one file and switch to `['BESI','KONKRIT']`, check 2 runs, result read-only, application `technical_circulation`/`submitted`, one `accepted_submissions` row with check 2's hash, `airs.review_versions` count 2.
- [ ] **Step 2: Run** `php artisan test --filter=LjupJourneyEndToEndTest` — Expected: PASS (all units exist; if it fails, the failure is a real integration gap — fix it in the owning task's files).
- [ ] **Step 3: Update README/AGENTS.md.**
- [ ] **Step 4: Run** `php artisan test` — Expected: full suite PASS.
- [ ] **Step 5: Commit** — `git commit -m "LJUP paid-check journey end-to-end test; docs"`
