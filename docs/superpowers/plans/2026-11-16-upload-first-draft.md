# Upload-first Draft (Part 1: File Uploads) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Applicants drop files, a folder or one ZIP onto a draft; the free local pre-check sorts each file into its checklist item or leaves it in a "Belum diisih" tray for the applicant to place.

**Architecture:** Draft documents may now be *unsorted* (`item_code` and `document_type_id` null). A new `DraftDropService` accepts loose files and ZIP entries as unsorted documents and queues the existing `PrecheckDraftDocument` job, which, after its local text read, ranks the file against the type's catalogue items (`DocumentSorter`) and places it (`DraftDocumentPlacer`). The checklist exposes the tray; payment and the amendment refuse while the tray is not empty.

**Tech Stack:** Laravel 13 / PHP 8.3, PostgreSQL 18 (`core` schema), Blade + `public/js/indecs.js` (no framework), PHPUnit 12, PHP `ZipArchive`.

**Spec:** `docs/superpowers/specs/2026-11-16-upload-first-draft-design.md` (section "Scope of this build": part 1 only — no field pre-fill, no `applicant_draft_suggestions`).

## Global Constraints

- No AI, cloud or network call anywhere in this path; local tools only (`LocalTextExtractor`). Missing tools → filename-only sorting, never a blocked draft.
- Allowed applicant file types stay `pdf, jpg, jpeg, png`; every stored file is sniffed (`finfo`), virus-scanned via `DocumentScanner::assertClean` (fail closed) and SHA-256 hashed.
- `kaunter.precheck.max_documents_per_draft` (60) counts unsorted files too.
- ZIP: no traversal, no nested archives, entry-count / uncompressed-size / ratio caps from `config('airs.limits.*')`; the ZIP itself is never stored.
- No drop, assign or remove when `PaidCheckJourneyService::draftEditable($draft)` is false (403 "Draf dikunci semasa semakan AIRS.").
- Extracted text is never stored (ADR-363).
- UI copy in Malay. Tray heading: **"Belum diisih"**. Locked/limits copy reuses existing strings.
- New config (in `config/kaunter.php` → `precheck`): `read_files_per_draft` = 20, `sort_threshold` = 3, `sort_margin` = 2, `max_zip_kb` = 122880 (stays under PHP `post_max_size` 128M).

## Review Focus

1. Same file dropped twice (or the same PDF inside a ZIP and loose) → second is rejected "Fail yang sama telah dimuat naik.", not a duplicate tray entry. (Task 2 test `test_duplicate_file_is_rejected`.)
2. Two files both best-matching one item → higher score placed, the other in the tray; an applicant-placed file is never displaced by auto-sorting. (Task 3 tests.)
3. A file in an item that stops applying after particulars change → appears in the tray and blocks payment, not silently dropped from hand-over. (Task 1 + Task 5 tests.)
4. A ZIP containing `../evil.pdf`, a nested `.zip`, or a `.exe` → unsafe archive refused whole; disallowed types skipped and listed. (Task 2 tests.)
5. Drop on a locked draft (check running / handed over) → 403, nothing stored. (Task 4 test `test_drop_on_locked_draft_is_forbidden`.)

---

### Task 1: Unsorted documents in the data model and checklist

**Files:**
- Create: `database/migrations/2026_11_16_000100_add_unsorted_draft_documents.php`
- Create: `app/Services/Precheck/DraftFileRules.php`
- Modify: `app/Models/ApplicantDraftDocument.php`, `app/Models/ApplicantDraft.php` (cast `title_provisional` bool)
- Modify: `app/Services/Precheck/DraftChecklist.php`, `app/Services/Precheck/DraftDocumentService.php` (use `DraftFileRules` instead of inline type checks)
- Test: `tests/Feature/Precheck/UnsortedDocumentsTest.php`

**Interfaces:**
- Produces: columns `core.applicant_draft_documents.item_code` nullable, `document_type_id` nullable, `sort_source` string(8) nullable (`auto`|`manual`), `sort_score` int nullable; `core.applicant_drafts.title_provisional` bool default false. Unsorted rows use `sequence = 0`.
- Produces: `DraftFileRules::forItem(ApplicantDraft $draft, string $itemCode): array{extensions: list<string>, mimes: list<string>, max_kb: int, document_type: DocumentType}`; `DraftFileRules::loosest(string $applicationTypeId): array{extensions: list<string>, mimes: list<string>, max_kb: int}` (largest `max_file_size_kb`, union of allowed extensions ∩ pdf/jpg/jpeg/png over the type's catalogue document types); `DraftFileRules::assertFits(UploadedFile|string $file, array $rules, string $field = 'file'): string` returns sniffed MIME or throws `ValidationException`.
- Produces: `DraftChecklist::for()` gains key `unsorted: list<ApplicantDraftDocument>` = all rows with `item_code` null **plus** the latest row of any item code not in the applicable items. `DraftChecklist::catalogueItems(string $applicationTypeId): list<array{code: string, name: string, keywords: list<string>, document_type_id: int}>` = every active catalogue item of the type regardless of particulars.

- [ ] **Step 1: Write failing tests** — `test_unsorted_document_appears_in_tray_not_in_items` (row with `item_code` null → in `unsorted`, no item's `document` is it, `required_ready` unchanged); `test_file_in_item_that_no_longer_applies_moves_to_tray` (TEL catalogue via `BuildsPrecheck::seedTelCatalogue`, upload to a conditional item, change particulars so it no longer applies → that document is in `unsorted`); `test_loosest_rules_take_largest_limit_and_union_of_types` (LJUP catalogue → `max_kb` 51200); `test_catalogue_items_ignore_particulars`.
- [ ] **Step 2: Run** `php artisan test --filter=UnsortedDocumentsTest` — expect FAIL (columns/methods missing).
- [ ] **Step 3: Implement** migration (drop/recreate nothing; `->nullable()->change()` on the two columns), the model casts, `DraftFileRules`, and the `DraftChecklist` changes. `DraftChecklist::for()` must key `$latest` only over rows with non-null `item_code`. Refactor `DraftDocumentService::upload` to call `DraftFileRules::forItem` + `assertFits` (behaviour unchanged) and to set `sort_source = 'manual'`.
- [ ] **Step 4: Run** `php artisan test --filter="UnsortedDocumentsTest|Precheck"` — expect PASS.
- [ ] **Step 5: Commit** `feat(precheck): unsorted draft documents and shared file rules`

### Task 2: Drop intake for loose files and ZIP

**Files:**
- Create: `app/Airs/DocumentProcessing/ZipSafety.php` (moved from `ArchiveExtractor::inspect` / `assertSafePath`; `ArchiveExtractor` delegates to it, behaviour unchanged)
- Create: `app/Services/Precheck/DraftDropService.php`, `app/Services/Precheck/DropResult.php`
- Modify: `app/Jobs/PrecheckDraftDocument.php` (constructor only: `__construct(string $documentId, bool $read = true)`)
- Modify: `config/kaunter.php`
- Test: `tests/Feature/Precheck/DraftDropServiceTest.php`

**Interfaces:**
- Consumes: `DraftFileRules::loosest`, `assertFits` (Task 1).
- Produces: `ZipSafety::inspect(\ZipArchive $zip, int $archiveBytes): array{entries: list<array{name: string, size: int}>, warnings: list<string>}` (throws `UnsafeArchiveException`).
- Produces: `DraftDropService::createDraft(User $owner, string $applicationTypeId): ApplicantDraft` — title `"Draf baharu — ".now()->format('d/m/Y')`, `title_provisional = true`, version 1, revision row, same 100-draft quota and verified-email rule as `PublicPortalController::store`.
- Produces: `DraftDropService::accept(User $owner, ApplicantDraft $draft, UploadedFile $file): DropResult`; `DropResult` is `final readonly` with `accepted: list<ApplicantDraftDocument>`, `rejected: list<array{name: string, reason: string}>`.
- Produces: each accepted file stored at `drafts/{draft}/inbox/{ulid}.{ext}`, row with `item_code` null, `document_type_id` null, `sequence` 0, `precheck_status` pending, `sort_source` null; job dispatched `afterCommit` with `$read = true` while the draft has fewer than `read_files_per_draft` documents that were dropped (`sort_source` auto or null at creation), else `false`.

- [ ] **Step 1: Write failing tests** — `test_loose_pdf_becomes_unsorted_and_queues_precheck` (`Queue::fake`, assert job pushed with `read` true); `test_zip_entries_become_unsorted_documents` (build a ZIP with 2 PDFs + 1 `.txt` → 2 accepted, rejected contains the `.txt` name with reason "Jenis fail tidak dibenarkan."); `test_unsafe_zip_is_refused_whole` (entry `../evil.pdf` → `ValidationException`, 0 rows); `test_nested_zip_entry_is_skipped`; `test_duplicate_file_is_rejected` (same bytes loose then inside a ZIP → rejected reason "Fail yang sama telah dimuat naik."); `test_infected_file_is_rejected` (scanner fake throws → rejected, nothing stored); `test_draft_document_limit_counts_unsorted`; `test_files_beyond_read_budget_are_queued_without_reading` (config `read_files_per_draft` 1 → second job `read` false); `test_create_draft_is_provisional`.
- [ ] **Step 2: Run** `php artisan test --filter=DraftDropServiceTest` — expect FAIL.
- [ ] **Step 3: Implement.** ZIP = sniffed `application/zip` and size ≤ `max_zip_kb`; open, `ZipSafety::inspect`, stream each entry to a temp file under `storage/app/precheck-tmp/{ulid}`, run the same per-file path as a loose file (loosest rules → scan → SHA dedupe against every row of the draft → store), delete the temp dir in `finally`. A per-file failure becomes a `rejected` entry carrying the `ValidationException` message; only an unsafe ZIP throws. Then run `php artisan test --filter=Airs` to prove `ArchiveExtractor` is unchanged.
- [ ] **Step 4: Run** `php artisan test --filter="DraftDropServiceTest|Airs"` — expect PASS.
- [ ] **Step 5: Commit** `feat(precheck): drop intake for files and ZIP archives`

### Task 3: Automatic sorting in the pre-check job

**Files:**
- Create: `app/Services/Precheck/DocumentSorter.php`, `app/Services/Precheck/DraftDocumentPlacer.php`
- Modify: `app/Jobs/PrecheckDraftDocument.php`
- Test: `tests/Unit/Precheck/DocumentSorterTest.php`, `tests/Feature/Precheck/AutoSortTest.php`

**Interfaces:**
- Consumes: `DraftChecklist::catalogueItems` (Task 1), `DraftFileRules::forItem` (Task 1), job `$read` flag (Task 2).
- Produces: `DocumentSorter::rank(string $fileName, string $text, array $items): list<array{code: string, score: int}>` sorted by score desc, then code asc. Normalise both strings: upper-case, `[_\-.]+` → space, collapse whitespace. Score per item = 3 × keywords found in the file name + 1 × words of the item code (split on `_`, length ≥ 4) found as file-name tokens + 2 × keywords found in the text.
- Produces: `DraftDocumentPlacer::autoPlace(ApplicantDraftDocument $document, array $ranked): bool` (true = placed). Inside a transaction that locks the draft row: winner = first; stay unsorted if `score < sort_threshold`, or the runner-up is within `sort_margin`, or the winner item's current document has `sort_source = 'manual'`, or it is `auto` with `sort_score >= score`, or the file breaks the item's `DraftFileRules`. Otherwise a displaced `auto` current document returns to the tray (`item_code`/`document_type_id` null, `sequence` 0), and the file takes `item_code`, `document_type_id`, `sequence = max + 1`, `sort_source = 'auto'`, `sort_score`.
- Produces: `DraftDocumentPlacer::place(ApplicantDraftDocument $document, string $itemCode, string $source): void` — the unconditional placement used by Task 4 (an existing current document stays as superseded history, like a re-upload).

- [ ] **Step 1: Write failing tests** — unit: `test_file_name_keyword_outranks_text_only_match`, `test_tie_orders_by_code`, `test_normalises_underscores_and_case` (`"laporan_fotometrik_v2.pdf"` ranks `laporan_fotometrik` first). Feature (`Queue` sync, fake `TextExtractor` bound in the container): `test_confident_file_is_placed_in_item`; `test_ambiguous_file_stays_unsorted`; `test_higher_score_displaces_lower_auto_placement_to_tray`; `test_manual_placement_is_never_displaced`; `test_unread_file_is_sorted_by_name_and_needs_confirmation` (`read` false → extractor never called, `ocr_unavailable` true, label `NeedsConfirmation` once placed); `test_matched_keywords_are_for_the_placed_item`.
- [ ] **Step 2: Run** `php artisan test --filter="DocumentSorterTest|AutoSortTest"` — expect FAIL.
- [ ] **Step 3: Implement.** In the job: skip the "superseded" check when `item_code` is null; when `$read` is false skip extraction and save `readable` true, `text_chars` null, `ocr_unavailable` true; for unsorted rows call `rank` (text = normalised extract or empty) then `autoPlace`; compute `matched_keywords` against the item it ends in (empty when unsorted). Keep `failed()` as is.
- [ ] **Step 4: Run** `php artisan test --filter="DocumentSorterTest|AutoSortTest|PrecheckJobTest"` — expect PASS.
- [ ] **Step 5: Commit** `feat(precheck): sort dropped files into checklist items`

### Task 4: Routes, controller actions and the tray/drop UI

**Files:**
- Modify: `routes/public.php`, `app/Http/Controllers/PortalDraftDocumentController.php`, `app/Http/Controllers/PublicPortalController.php` (`update()` sets `title_provisional` false; `edit()` renders the title input empty while provisional), `app/Services/Precheck/DraftDocumentService.php` (`assign`, `remove`)
- Create: `resources/views/public/drafts/partials/drop-zone.blade.php`, `resources/views/public/drafts/partials/unsorted.blade.php`
- Modify: `resources/views/public/drafts/edit.blade.php` (create mode: type select + drop zone first; existing fields inside `<details class="form-card"><summary>Atau isi butiran draf dahulu</summary>`), `resources/views/public/drafts/partials/checklist.blade.php` (include drop zone when editable, include tray), `public/js/indecs.js`, `public/css/indecs.css`
- Test: `tests/Feature/Precheck/DropUploadPagesTest.php`

**Interfaces:**
- Consumes: `DraftDropService` (Task 2), `DraftDocumentPlacer::place` (Task 3), `DraftChecklist::for()['unsorted']`, `DraftFileRules` (Task 1).
- Produces routes (inside the existing verified portal group, `throttle:20,1` on writes):
  - `POST /drafts/drop` → `portal.drafts.drop` (`application_type_id` required in portal types, `files[]` required, max 10 files) → creates draft, accepts files; JSON (`Accept: application/json`) `{draft_url, add_url, accepted, rejected}` else redirect to `edit#dokumen` with flash `status` and `drop_rejected`.
  - `POST /drafts/{draft}/documents` → `portal.drafts.documents.add` (`files[]`, max 10) → same response shape.
  - `POST /drafts/{draft}/documents/{document}/assign` → `portal.drafts.documents.assign` (`item_code` required) → `DraftDocumentService::assign(User, ApplicantDraftDocument, string $itemCode): void` (document must be in the tray; item must be applicable; `DraftFileRules::forItem` + `assertFits` on the stored file; then `place(..., 'manual')`).
  - `DELETE /drafts/{draft}/documents/{document}` → `portal.drafts.documents.remove` → `DraftDocumentService::remove(User, ApplicantDraftDocument): void` (tray documents only; deletes row and stored file).
- Tray partial: heading "Belum diisih", note "Pilih dokumen senarai semak bagi setiap fail, atau buang fail yang tidak diperlukan.", per file: name, label badge, `<select name="item_code">` of applicable items, "Letak" button, "Buang" button. When any tray file has `ocr_unavailable` true, show once above the list: "Bacaan automatik tidak tersedia untuk sebahagian fail — sila isih fail tersebut sendiri." (test `test_tray_explains_when_automatic_reading_was_unavailable`).
- Drop zone partial: `<div class="drop-zone" data-drop-url data-add-url>` text "Seret fail atau folder ke sini", buttons "Pilih fail" (`<input type=file name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">`) and "Pilih folder" (`webkitdirectory`), and a `<noscript>`-safe plain submit. JS: walk dropped directories via `webkitGetAsEntry`, skip names starting with `.` and `Thumbs.db`, send batches of ≤ 10 files and ≤ 100 MB (a ZIP or a file > 100 MB goes alone), first batch to `data-drop-url` on the create page then `add_url`, show per-file progress and the `rejected` reasons, then navigate to `draft_url#dokumen` (the existing poller takes over).

- [ ] **Step 1: Write failing tests** — `test_create_page_leads_with_drop_zone` (sees "Seret fail atau folder ke sini", "Pilih folder", "Atau isi butiran draf dahulu"); `test_drop_creates_draft_and_redirects` (multipart `files[]` → 1 draft, `title_provisional` true, redirect to edit); `test_drop_json_returns_add_url`; `test_add_to_existing_draft`; `test_tray_lists_unsorted_file_with_item_choices`; `test_assign_places_file_and_rejects_oversize` (item limit 1 KB → error, file stays in tray); `test_remove_deletes_only_tray_files` (placed file → 404/validation); `test_foreign_draft_is_not_found`; `test_drop_on_locked_draft_is_forbidden`; `test_saving_details_clears_provisional_title`.
- [ ] **Step 2: Run** `php artisan test --filter=DropUploadPagesTest` — expect FAIL.
- [ ] **Step 3: Implement** routes, controller methods, service methods, partials, JS and CSS (reuse `form-card`, `precheck-item`, `status-badge`, `button-*` classes; drop zone highlights on `dragover` with `.is-dragover`).
- [ ] **Step 4: Run** `php artisan test --filter="DropUploadPagesTest|Precheck|Portal"` — expect PASS.
- [ ] **Step 5: Commit** `feat(portal): drop zone, folder pick and unsorted tray`

### Task 5: Payment and amendment gates

**Files:**
- Modify: `app/Services/PaidCheck/CheckPaymentService.php` (`assertReadyForCheck`), `app/Services/PaidCheck/PaidCheckJourneyService.php` (`submitAmendment`), `resources/views/public/drafts/partials/checklist.blade.php` (gate text when tray not empty)
- Test: `tests/Feature/PaidCheck/UnsortedGateTest.php`

**Interfaces:**
- Consumes: `DraftChecklist::for()['unsorted']`, `ApplicantDraft::title_provisional`.

- [ ] **Step 1: Write failing tests** — `test_payment_refused_while_tray_not_empty` (error key `payment`, message "Isih atau buang fail dalam \"Belum diisih\" sebelum membayar."); `test_payment_refused_while_title_provisional` ("Isi tajuk cadangan dan simpan draf sebelum membayar."); `test_amendment_refused_while_tray_not_empty` (error key `check`, same tray message, journey stays `AwaitingApplicant`); `test_payment_allowed_once_tray_cleared`.
- [ ] **Step 2: Run** `php artisan test --filter=UnsortedGateTest` — expect FAIL.
- [ ] **Step 3: Implement** the two checks first in `assertReadyForCheck` (after the module check) and inside the `submitAmendment` transaction before `reviseDocuments`; gate block shows the tray message instead of the pay button when `unsorted` is non-empty.
- [ ] **Step 4: Run** `php artisan test --filter="UnsortedGateTest|PaidCheck"` — expect PASS.
- [ ] **Step 5: Commit** `feat(paid-check): block payment and amendment while files are unsorted`

### Task 6: LJUP sorting keywords and the pilot package

**Files:**
- Modify: `database/catalogues/ljup.v1.json` (add `precheck_keywords` per item; keep `"version": 0`)
- Modify: `tests/Feature/PaidCheck/LjupCatalogueFileTest.php`; fix any LJUP fixtures whose fake PDF text now misses keywords (`BuildsHandedOverLjup`, `LjupJourneyEndToEndTest`) by putting the item's keyword in the fixture text
- Test: `tests/Feature/Precheck/GreenCamourSortTest.php` (skipped unless `PLAN_REVIEW_GREEN_CAMOUR_PATH` is set, like `GreenCamourAcceptanceTest`)

**Interfaces:**
- Keywords (pending JKEJ confirmation): `pelan_lampu_jalan` LAMPU JALAN, STREET LIGHTING, JADUAL LAMPU · `pelan_kebenaran_merancang` KEBENARAN MERANCANG, PELAN TAPAK, SITE PLAN · `pelan_jalan_parit` JALAN DAN PARIT, ROAD AND DRAINAGE, DRAINAGE · `surat_jurutera_cs` JURUTERA KOMPETEN, COMPETENT ENGINEER, PERAKUAN · `pengesahan_tnb` TNB, TENAGA NASIONAL · `gambar_tapak` GAMBAR TAPAK, SITE PHOTO · `laporan_fotometrik` FOTOMETRIK, PHOTOMETRIC, DIALUX, LUX · `borang_pengemukaan` BORANG PENGEMUKAAN, SOP 4.

- [ ] **Step 1: Write failing tests** — `LjupCatalogueFileTest::test_every_item_has_sorting_keywords`; `GreenCamourSortTest::test_pilot_package_sorts_with_real_local_reader` (drop the ZIP or folder through `DraftDropService`, queue sync, real `LocalTextExtractor`: all 12 files accepted; the street-lighting plan lands in `pelan_lampu_jalan` and the photometric report in `laporan_fotometrik`; no file is placed in more than one item; print the tray for tuning).
- [ ] **Step 2: Run** `php artisan test --filter="LjupCatalogueFileTest|GreenCamourSortTest"` with `PLAN_REVIEW_GREEN_CAMOUR_PATH` set — expect FAIL.
- [ ] **Step 3: Implement** the keywords; tune `sort_threshold` / `sort_margin` defaults only if the pilot test shows a wrong placement (a wrong placement is worse than a file left in the tray).
- [ ] **Step 4: Run** the full suite `php artisan test` — expect all pass (1 skip without the env var).
- [ ] **Step 5: Republish to the dev DB** (payload with `"version"` set to the DB's current version, as done for v2): `php artisan catalogue:publish LJUP <adjusted copy> --actor=<system admin email>`; confirm `precheck_keywords` present on `document_requirements`.
- [ ] **Step 6: Commit** `feat(ljup): sorting keywords for the LJUP checklist`
