# Multi-file checklist items — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a catalogue item hold several current files (LJUP 1 = 5), sort Billion Site correctly, and stop text PDFs from reading blank or being OCR'd.

**Architecture:** `max_files` on document requirements flows through `DraftChecklist` (new `documents` list, worst-file label). Services, placer, job, views and the paid-check copy treat a multi-file item as a set of current files instead of a version chain.

**Tech Stack:** Laravel 13 / PHP 8.3, PostgreSQL 18, PHPUnit, Blade.

**Spec:** `docs/superpowers/specs/2026-11-16-multi-file-items-design.md`

## Global Constraints

- `max_files` 1–10, default 1; single-file items behave exactly as today (all existing tests stay green).
- Copy: upload button "Tambah fail"; full item message `Dokumen ini sudah mempunyai {n} fail. Buang satu fail sebelum menambah.`
- Never displace a file from a multi-file item; never hide a placed multi-file as history.
- Keep newline style of edited files; commit only own hunks of files with the owner's uncommitted edits (stage via blob, as before); never commit the workshop .docx.
- After code changes the job uses: `php artisan queue:restart` and confirm worker start times.

## Review Focus

- A multi-file item changed back to a single-file catalogue (`max_files` 5 → 1) with 3 files present: the newest is current, others are not lost (shown as history/tray, not deleted).
- Amendment with one of three files removed and one added: exactly one withdrawn, one added, one kept.
- Stale item (no longer applies) with several files: all go to the tray, none hidden.
- Duplicate upload of the same bytes into a multi-file item.
- Invalid UTF-8 from pdftotext with `-enc` unsupported by the binary: still no blank text.

---

### Task 1: Catalogue `max_files` and checklist shape

**Files:** migration `2026_11_16_000900_add_max_files_to_document_requirements.php` (smallint default 1); `app/Models/DocumentRequirement.php` (cast); `app/Services/DocumentCatalogueService.php` (whitelist + rule `integer|min:1|max:10`, store); `app/Services/Precheck/DraftChecklist.php` (`documents`, `max_files`, aggregate label, tray includes every file of a stale multi item); `app/Jobs/PrecheckDraftDocument.php` (no superseded skip when the item's `max_files` > 1); `database/catalogues/ljup.v1.json` (`borang_ljup1.max_files = 5`). Test: `tests/Feature/Precheck/MultiFileItemTest.php`.

**Produces:** checklist item keys `documents: list<ApplicantDraftDocument>`, `max_files: int`; `DraftChecklist::maxFiles(ApplicantDraft, string $itemCode): int`.

- [ ] Tests: `test_catalogue_stores_max_files`, `test_multi_item_lists_every_placed_file_and_worst_label` (two files, one NeedsConfirmation → item NeedsConfirmation), `test_single_item_still_shows_newest_only`, `test_stale_multi_item_sends_every_file_to_tray`, `test_job_reads_every_file_of_a_multi_item`, `test_ljup_catalogue_lets_ljup1_hold_five`.
- [ ] Run → FAIL; implement; run → PASS; commit `feat(precheck): checklist items can hold several files`.

### Task 2: Upload, sort, assign, remove, confirm

**Files:** `DraftDocumentService.php` (upload adds when multi, duplicate against any current file of the item, full → error; `remove` allowed for placed files of a multi item; `confirm` any current file; `assign` refuses full items); `DraftDocumentPlacer.php` (`autoPlace`: multi with room → place without displacement; full → stay in tray). Test: `MultiFileItemTest`.

- [ ] Tests: `test_upload_adds_to_multi_item_until_full`, `test_duplicate_bytes_refused_in_multi_item`, `test_autoplace_fills_multi_item_without_displacing`, `test_assign_into_full_item_refused`, `test_placed_file_in_multi_item_can_be_removed`, `test_confirm_each_file_in_multi_item`.
- [ ] Run → FAIL; implement; run plus `--filter='AutoSortTest|UnsortedDocumentsTest|DraftDocument'` → PASS; commit `feat(precheck): add, sort and remove files in multi-file items`.

### Task 3: Item window

**Files:** `resources/views/public/drafts/partials/checklist.blade.php` (dialog lists `documents`, per-file confirm/Buang, "Tambah fail" when multi and not full; owner has uncommitted edits — stage own hunk only). Test: `tests/Feature/Precheck/MultiFilePagesTest.php`.

- [ ] Tests: dialog shows all three names, three remove forms, "Tambah fail", and `(3/5 fail)`; single item still shows "Muat naik semula".
- [ ] Run → FAIL; implement; PASS; commit `feat(portal): item window lists every file of a multi-file item`.

### Task 4: Paid check and amendment

**Files:** `app/Services/PaidCheck/PaidCheckJourneyService.php` (`copyDocuments` copies every file of `documents`; `reviseDocuments` matches multi-file items by sha256: keep, add new, withdraw missing). Test: `tests/Feature/PaidCheck/MultiFileCheckTest.php`.

- [ ] Tests: `test_paid_check_receives_every_file_of_ljup1`, `test_amendment_keeps_adds_and_withdraws_within_multi_item`.
- [ ] Run → FAIL; implement; PASS plus `--filter=PaidCheck` → PASS; commit `feat(paidcheck): copy every file of multi-file items`.

### Task 5: Keywords, text reading, pilot rerun

**Files:** `database/catalogues/ljup.v1.json` (keywords "LJUP" on borang_ljup1, "COVER LETTER" on borang_pengemukaan); `app/Services/Precheck/LocalTextExtractor.php` (`-enc UTF-8`, `mb_scrub` on all returned text); local `.env` (`KAUNTER_PDFTOTEXT_BINARY` full path; not committed). Tests: `tests/Unit/Precheck/LocalTextExtractorTest.php` or existing extractor test (invalid UTF-8 scrubbed), `LjupCatalogueFileTest` keywords.

- [ ] Tests → FAIL; implement; PASS; full suite; commit `fix(precheck): read embedded PDF text as UTF-8; LJUP and cover-letter keywords`.
- [ ] Republish dev LJUP catalogue (next version), `queue:restart`, drop Billion Site and Green Camour into new pilot drafts; expect Billion Site LJUP/Umum/Khusus under borang_ljup1 and Cover Letter under borang_pengemukaan. Record the result.
