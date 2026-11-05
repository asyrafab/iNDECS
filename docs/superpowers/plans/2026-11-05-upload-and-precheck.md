# Upload and Free Pre-check (Kaunter Maya, step 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A PSP records their project particulars, sees the documents their
project needs (from the step 2 rules), uploads each one, and gets a free
pre-check: virus scan, readable, and expected keywords found by embedded text
or local OCR. The result uses the contract's four labels, and a gate shows
when the draft is ready for paid AIRS review.

**Architecture:**
- **Data.** Particulars become columns on `core.applicant_drafts`, and are
  included in revision snapshots. Uploads go in a new
  `core.applicant_draft_documents` table, where each upload is a new row per
  item and the latest row is current. Catalogue items gain
  `precheck_keywords`.
- **Services.**
  - `DraftChecklist` combines `ChecklistRuleEvaluator` with the latest
    uploads to give each item its label.
  - `DraftDocumentService` handles scan, store, dedupe and dispatch.
  - The `PrecheckDraftDocument` job runs on the `precheck` queue and uses a
    `TextExtractor` contract.
  - `LocalTextExtractor` runs pdftotext, then Ghostscript plus Tesseract. A
    fake extractor is used in tests.
- **UI.** The draft editor's file side gains a "Butiran projek" section and a
  "Dokumen" checklist. Fail saya rows show "Dokumen x/y". AMIRA's mood
  follows the checklist.

**Tech Stack:** Laravel 12 / PHP 8.3, PostgreSQL, the database queue,
Symfony Process, local binaries (`pdftotext`, Ghostscript `gswin64c`/`gs`,
`tesseract`, ClamAV `clamscan`), Blade and vanilla JS.

**Spec:** `docs/superpowers/specs/2026-11-05-public-portal-kaunter-maya-design.md`
(§3, §4.1, §4.3, §8.6a, and §9 step 3). Contract:
`docs/integration/indecs-airs-contract.md` §3.

## Global Constraints

- No AI, no cloud OCR and no network calls in this step. Text comes only from embedded PDF text and local Tesseract (`msa+eng`). When OCR is unavailable, the result degrades to "Perlu pengesahan pemohon", never to a cloud fallback.
- Only these four labels are used: `Dilampirkan`, `Belum dilampirkan`, `Tidak dapat dibaca`, `Perlu pengesahan pemohon`. "Sedang disemak" is used only while a check is pending. Never use lulus, gagal, pass, fail, a score or any technical finding. Every pre-check view shows the sentence: "Semakan ini membantu menyediakan dokumen sebelum penyerahan. Kandungan teknikal dan pematuhan akan disemak dalam proses rasmi."
- Every upload goes through the existing `App\Contracts\DocumentScanner` (ClamAV) before storage. It fails closed: no file and no row are kept when the scan is unavailable or detects something.
- Allowed extensions and maximum size come from the item's `document_types` row (`allowedExtensions()`, `max_file_size_kb`). Files are stored on the private `local` disk at `drafts/{draft_id}/{code}/{n}.{ext}` and downloaded only by the owner, as an attachment with `nosniff`.
- Budgets go in a new `precheck` block in `config/kaunter.php` (env-overridable):
  - `ocr_max_pages` = 10;
  - `process_timeout` = 60;
  - `min_text_chars` = 30;
  - `max_documents_per_draft` = 60;
  - binary paths `pdftotext`, `ghostscript`, `tesseract`;
  - `tesseract_langs` = `msa+eng`.
- One active check per draft: the job uses `ShouldBeUnique` keyed by `draft_id`, plus a status guard. The job runs on queue `precheck` with `tries = 1`. Re-uploading a file with the same SHA-256 as that item's current upload stores nothing new and does not re-run.
- Only the owner may use these routes: every query goes through `ApplicantDraft::query()->ownedBy($user)`, and foreign IDs return 404. The account must be verified, as `requireVerified` already enforces.
- Drafts are still not applications. Nothing here writes `applications`, payments, `core.accepted_submissions` or AIRS jobs.
- UI: Bahasa Melayu, tokens only, existing classes, desktop-only upload controls (below 901px show "Muat naik dokumen menggunakan komputer").
- Work on branch `feat/kaunter-maya-shell`, staging only files this plan touches. The tracked files in this plan have no other owner edits.

## Review Focus

1. **A file whose extension lies** (a text file named `.pdf`, or an image that is really HTML) is rejected before storage, with a Malay message. Test in Task 3.
2. **The virus scanner is unavailable:** the upload fails with the scanner's Malay message, and no row or file is left behind. Test in Task 3.
3. **A scanned PDF with no embedded text** gets OCR on at most `ocr_max_pages` pages, and a timeout leads to "Perlu pengesahan pemohon", not an error page. Test in Task 4 with the fake extractor reporting `ocrUnavailable`.
4. **Particulars change after uploads** (for example storeys go from 4 to 2): items that no longer apply leave the checklist, and their uploads are kept but not counted. Test in Task 2.
5. **Another applicant** guesses a draft or document ID for upload, download or confirm and gets 404. Test in Task 5.

---

### Task 1: Particulars and keywords schema

**Files:**
- Create: `database/migrations/2026_11_05_000400_add_particulars_and_precheck.php`, which:
  - adds to `core.applicant_drafts`: `work_type` string(30) nullable, `building_category` string(30) nullable, `storeys` smallint nullable, `floor_area` numeric(12,2) nullable, `site_conditions` jsonb nullable;
  - adds to `document_requirements`: `precheck_keywords` jsonb nullable;
  - creates `core.applicant_draft_documents`: `id` ulid pk, `draft_id` ulid fk restrict, `item_code` string(40), `document_type_id` fk, `sequence` unsigned int, `original_name` string(255), `path` string(500), `mime` string(100), `size_bytes` bigint, `sha256` char(64), `uploaded_by` fk users, `precheck_status` string(12) default `pending` (`pending|done`), `readable` boolean nullable, `text_chars` int nullable, `matched_keywords` jsonb nullable, `ocr_used` boolean default false, `ocr_unavailable` boolean default false, `confirmed_at` timestamp nullable, timestamps. Unique on (`draft_id`, `item_code`, `sequence`), and index on (`draft_id`, `item_code`).
- Modify: `app/Models/ApplicantDraft.php` (fillable plus casts: `storeys` int, `floor_area` float, `site_conditions` array; `documents()` HasMany; `particulars(): array`, which returns the evaluator particulars shape)
- Modify: `app/Http/Controllers/PublicPortalController.php`:
  - `validated()` accepts the five particulars, validated with keys from `config('kaunter.*')`: `storeys` integer 1–100, `floor_area` numeric 1–1000000, `site_conditions.*` in the config list;
  - `recordRevision()` snapshot includes them.
- Modify: `app/Services/BangunanDocumentCatalogueService.php`, so `items.*.precheck_keywords` is a nullable array of at most 10 strings, each 2–60 characters, stored trimmed and upper-cased.
- Create: `app/Models/ApplicantDraftDocument.php` (`$guarded = ['*']`; casts; `draft()`)
- Test: `tests/Feature/Precheck/ParticularsTest.php`

**Interfaces:**
- Produces: `ApplicantDraft::particulars(): array{work_type: ?string, building_category: ?string, storeys: ?int, floor_area: ?float, site_conditions: list<string>}`, and `ApplicantDraftDocument`.

- [ ] **Step 1: Write the failing tests:**
  - `test_draft_saves_particulars_and_snapshots_them` (store and update with `work_type=tambahan`, `storeys=3`, `site_conditions=['strata']`; the revision snapshot contains them);
  - `test_particulars_reject_unknown_values` (`work_type=kapal`, `storeys=0` and `site_conditions=['bulan']` each produce errors);
  - `test_catalogue_keywords_are_trimmed_uppercased_and_bounded` (publish with `[' hakmilik ', 'No. Lot']` and expect `['HAKMILIK','NO. LOT']`; 11 keywords produce an error).
- [ ] **Step 2: Run and confirm they fail.** Run: `php artisan test --filter=ParticularsTest`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="ParticularsTest|PublicPortalTest|ChecklistRulesTest"`. Expected: PASS
- [ ] **Step 5: Commit** (`feat(precheck): project particulars and check keywords`)

---

### Task 2: DraftChecklist

**Files:**
- Create: `app/Services/Precheck/DraftChecklist.php`, `app/Enums/PrecheckLabel.php`
- Test: `tests/Feature/Precheck/DraftChecklistTest.php`

**Interfaces:**
- `enum PrecheckLabel: string`:
  - `Attached = 'Dilampirkan'`
  - `Missing = 'Belum dilampirkan'`
  - `Unreadable = 'Tidak dapat dibaca'`
  - `NeedsConfirmation = 'Perlu pengesahan pemohon'`
  - `Pending = 'Sedang disemak'`

  It also has `badgeClass(): string`, which returns `status-compliant`, `status-pending`, `status-critical`, `status-attention` and `status-info` respectively.
- `DraftChecklist::for(ApplicantDraft $draft): array` returns:
  - `available: bool`, which is false when the type has no published `core.document_catalogues` row;
  - `items: list<array{code, name, guidance, is_required, conditional, keywords: list<string>, label: PrecheckLabel, document: ?ApplicantDraftDocument}>`;
  - `required_total: int`;
  - `required_ready: int`;
  - `ready: bool`, which is `available && required_total > 0 && required_ready === required_total`.
- Label rules for the current (highest `sequence`) document of an item:
  - no document gives `Missing`;
  - `precheck_status=pending` gives `Pending`;
  - `readable === false` gives `Unreadable`;
  - `confirmed_at` set gives `Attached`;
  - `ocr_unavailable` gives `NeedsConfirmation`;
  - keywords configured and `matched_keywords` empty gives `NeedsConfirmation`;
  - otherwise `Attached`.
- Keywords come from `document_requirements.precheck_keywords`, and the evaluator output is extended to include `keywords`.
- Modify: `ChecklistRuleEvaluator::documentsFor` adds `'keywords' => $item->precheck_keywords ?? []`.

- [ ] **Step 1: Write the failing tests:**
  - `test_no_catalogue_means_unavailable` (PKM draft);
  - `test_labels_follow_document_state` (build documents directly with `forceFill` for each state, and assert each label);
  - `test_ready_only_when_all_required_items_attached` (an optional item missing still allows ready);
  - `test_items_follow_particulars_and_orphan_uploads_are_not_counted` (an upload for `pelan_struktur` while storeys is 4, then storeys changes to 2: the item disappears and `required_total` drops);
  - `test_latest_sequence_wins`.

  Seed a TEL catalogue as `system_admin` through `BangunanDocumentCatalogueService` (`$this->seed()`), and use a TEL draft.
- [ ] **Step 2: Run and confirm they fail.** Run: `php artisan test --filter=DraftChecklistTest`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run and confirm they pass.**
- [ ] **Step 5: Commit** (`feat(precheck): draft checklist with contract labels`)

---

### Task 3: Upload service

**Files:**
- Create: `app/Services/Precheck/DraftDocumentService.php`
- Modify: `config/kaunter.php` (the `precheck` block from Global Constraints)
- Test: `tests/Feature/Precheck/DraftDocumentServiceTest.php`

**Interfaces:**
- `DraftDocumentService`:
  - `upload(User $owner, ApplicantDraft $draft, string $itemCode, UploadedFile $file): ApplicantDraftDocument`
  - `confirm(User $owner, ApplicantDraftDocument $document): void`
- `upload`:
  1. The owner and a verified account are required; otherwise 404 or 403, as the existing controller does.
  2. `$itemCode` must be one of `DraftChecklist::for($draft)['items']` codes; otherwise a ValidationException on `file`, "Dokumen ini tidak diperlukan untuk projek anda."
  3. Validate the file using the item's document type: `mimes:` from `allowedExtensions()`, `max:` from `max_file_size_kb`. Content sniffing uses `mimetypes:application/pdf,image/jpeg,image/png`, restricted to the allowed ones. The attribute name is "fail dokumen".
  4. Enforce `max_documents_per_draft`.
  5. If the SHA-256 equals the item's current document, return that document unchanged.
  6. Call `DocumentScanner::assertClean($file->getRealPath())` before storing anything.
  7. Store the file, insert the row with `sequence = max + 1` and `precheck_status = pending` inside a transaction (deleting the file on failure), then `PrecheckDraftDocument::dispatch($document->id)` after commit.
- `confirm`: the owner only. Allowed only when the label is `NeedsConfirmation`; it sets `confirmed_at`. An `Unreadable` file cannot be confirmed (ValidationException "Fail tidak dapat dibaca. Sila muat naik semula.").

- [ ] **Step 1: Write the failing tests.** Use `Queue::fake()`, `Storage::fake('local')`, and a fake `DocumentScanner` bound with `$this->app->instance` (clean or throwing):
  - `test_upload_scans_stores_and_dispatches_once`;
  - `test_same_file_again_is_not_reprocessed`;
  - `test_lying_extension_is_rejected_in_malay_before_storage`;
  - `test_scanner_unavailable_keeps_nothing`;
  - `test_item_not_in_checklist_is_rejected`;
  - `test_document_quota_per_draft`;
  - `test_confirm_only_for_needs_confirmation`.
- [ ] **Step 2: Run and confirm they fail.** Run: `php artisan test --filter=DraftDocumentServiceTest`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run and confirm they pass.**
- [ ] **Step 5: Commit** (`feat(precheck): scanned, deduplicated draft uploads`)

---

### Task 4: Text extraction and the pre-check job

**Files:**
- Create: `app/Contracts/TextExtractor.php`, `app/Services/Precheck/LocalTextExtractor.php`, `app/Jobs/PrecheckDraftDocument.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind `TextExtractor` to `LocalTextExtractor`)
- Test: `tests/Feature/Precheck/PrecheckJobTest.php`, `tests/Feature/Precheck/LocalTextExtractorTest.php`

**Interfaces:**
- `interface TextExtractor { public function extract(string $absolutePath, string $mime): ExtractedText; }`
- `final readonly class App\Services\Precheck\ExtractedText { public function __construct(public string $text, public bool $ocrUsed, public bool $ocrUnavailable, public bool $corrupt) {} }`
- `LocalTextExtractor`, using Symfony `Process` with `process_timeout` and never a shell string:
  - **PDF:** `pdftotext -layout -l {ocr_max_pages} file -`. A non-zero exit means `corrupt`. If the stripped text is shorter than `min_text_chars`, render pages 1..`ocr_max_pages` with Ghostscript (`-dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r200 -dFirstPage=1 -dLastPage=N`) into a temp dir, run `tesseract page.png - -l {langs}` per page, set `ocrUsed=true`, and delete the temp dir.
  - **JPEG/PNG:** run tesseract directly, with `ocrUsed=true`.
  - **Failures:** a missing binary, a timeout or any OCR failure gives `ocrUnavailable=true`, with whatever text was obtained.
- `PrecheckDraftDocument`:
  - implements `ShouldQueue` and `ShouldBeUnique` (`uniqueId` = the draft id), with `onQueue('precheck')`, `$tries = 1` and `$timeout = 300`;
  - loads the document, and skips it if it is not pending or not the item's current sequence;
  - extracts the text, then sets `readable = ! corrupt && (chars >= min_text_chars || ocrUnavailable)`. When OCR is unavailable, readability is unknown, so the result defers to the PSP's confirmation;
  - sets `matched_keywords` to the item's keywords found case-insensitively after collapsing whitespace, plus `text_chars`, `ocr_used`, `ocr_unavailable` and `precheck_status = done`;
  - never stores the extracted text itself, to avoid personal data at rest.

- [ ] **Step 1: Write the failing tests.**
  - `PrecheckJobTest`, binding a fake `TextExtractor` that returns set results:
    - `test_keyword_match_gives_attached`;
    - `test_no_keyword_match_gives_needs_confirmation`;
    - `test_corrupt_gives_unreadable`;
    - `test_ocr_unavailable_gives_needs_confirmation_not_error`;
    - `test_superseded_document_is_skipped`;
    - `test_job_is_unique_per_draft_and_on_precheck_queue`.
  - `LocalTextExtractorTest`, marked `@group local-tools` and skipped with `markTestSkipped` unless the binaries exist:
    - `test_embedded_text_pdf_is_read_without_ocr`, using a tiny generated PDF with text;
    - `test_png_is_ocred`, using a PNG rendered with GD containing "HAKMILIK" at a large font;
    - `test_missing_binary_reports_ocr_unavailable`, with the config pointed at a missing path.
- [ ] **Step 2: Run and confirm they fail.** Run: `php artisan test --filter="PrecheckJobTest|LocalTextExtractorTest"`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run and confirm they pass.** The local-tools tests should run on this machine.
- [ ] **Step 5: Commit** (`feat(precheck): local text extraction and pre-check job`)

---

### Task 5: Portal routes and the draft checklist UI

**Files:**
- Modify: `routes/public.php`, inside `public.access`:
  - `portal.drafts.documents.store`: POST `/drafts/{draft}/documents/{code}`, `throttle:20,1`;
  - `portal.drafts.documents.confirm`: POST `/drafts/{draft}/documents/{document}/confirm`;
  - `portal.drafts.documents.download`: GET `/drafts/{draft}/documents/{document}`;
  - `portal.drafts.checklist`: GET `/drafts/{draft}/checklist`, which returns the rendered checklist partial for polling.
- Create: `app/Http/Controllers/PortalDraftDocumentController.php`
- Create: `resources/views/public/drafts/partials/particulars.blade.php`, `resources/views/public/drafts/partials/checklist.blade.php`
- Modify: `resources/views/public/drafts/edit.blade.php` (particulars inside the existing form, for Bangunan types only; the checklist below the form, only when the draft exists)
- Modify: `public/js/portal.js` (poll `portal.drafts.checklist` every 4 s while any `[data-precheck-pending]` exists, replace the section, and stop after 5 minutes)
- Modify: `public/css/indecs.css` (`.precheck-*`, tokens only; below 901px hide `.precheck-upload` and show `.precheck-desktop-note`)
- Modify: `app/Http/Controllers/PublicPortalController.php` (`edit` passes `checklist` and computes the AMIRA mood, see Task 6)
- Test: `tests/Feature/Precheck/PortalPrecheckPagesTest.php`

**Interfaces:**
- Consumes: `DraftChecklist::for`, `DraftDocumentService`, `PrecheckLabel`.
- The checklist partial shows:
  - the header "Dokumen diperlukan", the counter "{ready}/{total} dokumen wajib sedia", and the contract sentence;
  - one row per item: name, "Wajib" or "Pilihan", the guidance text, a label badge, the current file name with a download link, an upload form (`multipart`, `accept` from the allowed extensions), and a "Saya sahkan dokumen ini betul" button when the label is NeedsConfirmation;
  - the gate box: "Sedia untuk semakan AIRS" when `ready`, otherwise "Lengkapkan dokumen wajib untuk meneruskan", plus a disabled button "Bayar semakan AIRS · dibuka tidak lama lagi";
  - "Senarai semak belum tersedia untuk jenis permohonan ini." when the checklist is unavailable.

- [ ] **Step 1: Write the failing tests:**
  - `test_owner_sees_items_labels_and_contract_sentence`;
  - `test_upload_then_confirm_flow_through_routes` (with the fake scanner and `Queue::fake`, then run the job synchronously with the fake extractor);
  - `test_other_applicant_gets_404_on_upload_download_confirm_and_checklist`;
  - `test_download_is_attachment_nosniff_owner_only`;
  - `test_unavailable_catalogue_message_for_pkm`;
  - `test_no_payment_or_application_side_effects` (`applications`, `core.accepted_submissions` and `integration.airs_reviews` counts stay 0);
  - `test_labels_never_use_pass_fail_wording` (the page contains none of `Lulus`, `Gagal`, `Skor`).
- [ ] **Step 2: Run and confirm they fail.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run, then check in the browser** with the local demo applicant, a TEL draft and a published TEL catalogue that has keywords. Check desktop upload, polling to the final label, and the phone width showing the desktop note.
- [ ] **Step 5: Commit** (`feat(portal): draft documents and free pre-check UI`)

---

### Task 6: Fail saya progress, AMIRA mood and catalogue keywords

**Files:**
- Modify: `app/Services/Portal/PortalBoard.php`:
  - drafts whose checklist has an Unreadable or NeedsConfirmation item move to the `action` section, with a to-do "{title}: {n} dokumen perlu tindakan";
  - `for()` accepts an optional `ApplicantDraft $focus`;
  - mood: `Think` when the focus draft has a Pending item; `Alert` for to-dos; `Happy` when the focus draft is `ready`.
- Modify: `resources/views/public/partials/file-board.blade.php` (a row badge "Dokumen {ready}/{total}" when the checklist is available)
- Modify: `resources/views/bangunan/partials/catalogue-item.blade.php` (an input "Kata kunci semakan (pisahkan dengan koma)" named `items[i][precheck_keywords_text]`)
- Modify: `app/Http/Controllers/BangunanDocumentCatalogueController.php` (split `precheck_keywords_text` into `precheck_keywords` before publishing; `edit` joins them for display)
- Test: extend `tests/Feature/Portal/PortalBoardTest.php` and `tests/Feature/Checklist/ChecklistRulesPageTest.php`

**Interfaces:**
- Consumes: `DraftChecklist::for` (Task 2).
- For N+1 safety, `PortalBoard` computes checklists only for the at most 20 active drafts shown, with one catalogue query per distinct type.

- [ ] **Step 1: Write the failing tests:**
  - `test_draft_needing_action_moves_to_action_section_with_todo`;
  - `test_focus_draft_pending_makes_amira_think`;
  - `test_focus_draft_ready_makes_amira_happy`;
  - `test_keywords_text_round_trip_on_catalogue_page`.
- [ ] **Step 2: Run and confirm they fail.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `composer test`. Expected: all green.
- [ ] **Step 5: Commit** (`feat(precheck): board progress, AMIRA mood and keyword editing`)

---

### Task 7: Decision record and documentation

**Files:**
- Create: `docs/adr/ADR-363-free-precheck.md`. Cover:
  - the particulars;
  - the labels and their rules;
  - the keywords;
  - the local-only extraction path and its budgets;
  - fail-closed scanning;
  - the `precheck` queue and uniqueness;
  - no extracted text at rest;
  - the gate;
  - what step 5 consumes (`DraftChecklist::ready`).
- Modify: `docs/adr/README.md`, `AGENTS.md` (section "Free applicant pre-check"), `docs/runbooks/public-portal.md`, and a new `docs/runbooks/precheck-worker.md` covering:
  - installing or configuring ClamAV, Tesseract (with `msa`), Ghostscript and poppler;
  - the env keys;
  - running `php artisan queue:work --queue=precheck --timeout=300`;
  - local-tools tests.
- [ ] **Step 1: Write the documents.**
- [ ] **Step 2: Run** `composer test`. Expected: green.
- [ ] **Step 3: Commit** (`docs: ADR-363 free applicant pre-check`)
