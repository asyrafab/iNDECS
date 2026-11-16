# Draft prefill from dropped documents — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fill an upload-first draft's empty title, site address and LJUP lighting type from its dropped documents, for the applicant to check and save.

**Architecture:** A pure `DraftParticularsReader` parses the text the existing `PrecheckDraftDocument` job already extracts; a `DraftPrefiller` applies the values under the draft row lock (empty/provisional fields only, version bump, revision row, `prefill_sources`). The form and the checklist poll show the source; portal.js merges values into still-empty boxes of an open form.

**Tech Stack:** Laravel 13 / PHP 8.3, PostgreSQL 18 (schema `core`), PHPUnit, plain JS (`public/js/portal.js`).

**Spec:** `docs/superpowers/specs/2026-11-16-draft-prefill-design.md`

## Global Constraints

- No AI, no network, no stored extracted text before payment (ADR-363); reading only inside the existing job.
- Never overwrite applicant input: title only while `title_provisional`; address/lighting only while empty (null, `''` or `[]`).
- A filled value is unconfirmed until the applicant saves; the payment gate for a provisional title stays.
- Limits: title ≤ 500 chars, site_address ≤ 2000 chars; lighting values are keys of `config('airs.lighting_types')` (`BESI`, `KONKRIT`, `SOLAR`).
- Note copy, exact: `Diisi daripada {original_name} — sila semak.`
- Preserve each file's newline style when editing (several views are CRLF).
- Never commit `docs/Jemputan bengkel URS iNDECS 231126.docx`.

## Review Focus

- Applicant saves the open form while the job fills the draft: no version conflict, typed values win.
- Two letters in one package: preferred (PERMOHONAN / cover-letter item) wins regardless of order; a non-preferred file never replaces a preferred value.
- OCR noise: subject split across lines, `PER:` vs `PER :`, missing `DI ATAS` — reader returns nothing rather than garbage.
- Non-LJUP drafts never get `lighting_types`.
- Draft deleted (soft delete) or locked for payment while the job runs: no fill.

---

### Task 1: Reader and storage column

**Files:**
- Create: `database/migrations/2026_11_16_000800_add_prefill_sources_to_applicant_drafts.php` (jsonb `prefill_sources` nullable on `core.applicant_drafts`)
- Modify: `app/Models/ApplicantDraft.php` (cast `prefill_sources` => `array`)
- Create: `app/Services/Precheck/DraftParticularsReader.php`
- Test: `tests/Unit/Precheck/DraftParticularsReaderTest.php`

**Interfaces:**
- Produces: `DraftParticularsReader::read(string $text): array{title?: string, site_address?: string, lighting_types?: list<string>}` — input is any text; it normalises whitespace and upper-cases itself.

- [ ] **Step 1: Write the failing tests** using the Green Camour OCR subject as a fixture constant:

```php
private const LETTER = "Tuan, PERMOHONAN KELULUSAN PELAN LAMPU JALAN BAGI CADANGAN PEMBANGUNAN YANG\nTERDIRI DARIPADA: FASA 1: i) 14 UNIT KEDAI PEJABAT BERKEMBAR 2 TINGKAT ii) 2 UNIT KEDAI PEJABAT SESEBUAH 2 TINGKAT iii) 1 UNIT PENCAWANG ELEKTRIK PADAT\nDI ATAS SEBAHAGIAN LOT 22067 (PT6182), LEBUHRAYA BANDAR CASSIA, BANDAR CASSIA BATU\nKAWAN, MUKIM 13, SEBERANG PERAI SELATAN.\nUNTUK TETUAN GREEN CAMOUR PROPERTY SDN. BHD. Per : Permohonan Kelulusan";
```

  - `test_reads_title_from_cover_letter_subject`: title === `'CADANGAN PEMBANGUNAN YANG TERDIRI DARIPADA: FASA 1: I) 14 UNIT KEDAI PEJABAT BERKEMBAR 2 TINGKAT II) 2 UNIT KEDAI PEJABAT SESEBUAH 2 TINGKAT III) 1 UNIT PENCAWANG ELEKTRIK PADAT'`
  - `test_reads_site_address_after_di_atas`: site_address === `'SEBAHAGIAN LOT 22067 (PT6182), LEBUHRAYA BANDAR CASSIA, BANDAR CASSIA BATU KAWAN, MUKIM 13, SEBERANG PERAI SELATAN'`
  - `test_text_without_the_letter_pattern_gives_nothing`: lux-report text (`'LED STREET LIGHT (ECO PRIME SERIES) 150W QUANTITY : 23 UNITS'`) → `[]`
  - `test_lighting_type_only_when_exactly_one_is_named`: `'TIANG KONKRIT 10M'` → `['KONKRIT']`; `'LAMPU JALAN SOLAR'` → `['SOLAR']`; `'TIANG BESI GALVANI'` → `['BESI']`; `'TIANG KONKRIT ... TIANG BESI'` → no key; `'TENAGA SURIA'` alone → no key.
  - `test_values_are_capped`: a 600-char title → `mb_strlen === 500`.

- [ ] **Step 2: Run** `php artisan test --filter=DraftParticularsReaderTest` — Expected: FAIL, class not found.

- [ ] **Step 3: Implement.** Title: `/PERMOHONAN\b.*?\bBAGI\s+(CADANGAN\b.+?)(?=\s+DI ATAS\b|\s+UNTUK TETUAN\b|\s+PER\s?:|$)/u`. Address: `/\bDI ATAS\s+(.+?)(?=\s*\.\s|\s+UNTUK\b|\s+PER\s?:|$)/u`. Trim spaces and trailing `.,;:`. Lighting: SOLAR via `/\b(LAMPU( JALAN)?|LIGHT|TIANG)\s+SOLAR\b|\bSOLAR\s+(STREET\s+)?LIGHT/u`; KONKRIT via `/\bTIANG\s+(LAMPU\s+)?KONKRIT\b|\bCONCRETE\s+POLE/u`; BESI via `/\bTIANG\s+(LAMPU\s+)?(BESI|GALVANI\w*)\b|\bGALVANI[SZ]ED\s+(STEEL\s+)?POLE/u`; set only when exactly one type matched. Add the migration and cast.

- [ ] **Step 4: Run** the same filter — Expected: PASS. Run `php artisan migrate` on the dev DB.

- [ ] **Step 5: Commit** `feat(precheck): read draft details from cover-letter text`.

### Task 2: Apply in the pre-check job

**Files:**
- Create: `app/Services/Precheck/DraftPrefiller.php`
- Modify: `app/Jobs/PrecheckDraftDocument.php` (after `autoPlace` + refresh, when `$this->read` and text is non-empty)
- Test: `tests/Feature/Precheck/DraftPrefillTest.php` (scanner fake + fake TextExtractor returning fixed text, as in `AutoSortTest`)

**Interfaces:**
- Consumes: `DraftParticularsReader::read`.
- Produces: `DraftPrefiller::apply(ApplicantDraftDocument $document, array $found): bool` (true when the draft changed). `prefill_sources` shape: `{title|site_address|lighting_types: {document_id: string, name: string, preferred: bool}}`.

- [ ] **Step 1: Write the failing tests:**
  - `test_fills_provisional_title_and_empty_address_from_dropped_letter`: draft from `DraftDropService::createDraft`, job run on a file whose fake text is the LETTER → title/address as in Task 1, `title_provisional` still true, `prefill_sources['title']['name'] === 'SURAT PERMOHONAN.pdf'`, version 2, a revision row with version 2 whose snapshot has `"prefill": true`.
  - `test_never_overwrites_applicant_values`: draft with `title_provisional=false` and an address → unchanged, version unchanged, no new revision.
  - `test_preferred_source_replaces_non_preferred_but_not_reverse`: `LAMPIRAN.pdf` then `SURAT PERMOHONAN.pdf` with different titles → the PERMOHONAN title wins; in reverse order the PERMOHONAN title stays.
  - `test_lighting_type_only_for_ljup`: LJUP draft + text `TIANG KONKRIT` → `['KONKRIT']`; TEL draft same text → `lighting_types` null.
  - `test_locked_or_deleted_draft_is_not_filled`: draft with a pending/paid check payment (`draftEditable` false) or soft-deleted → unchanged.
  - `test_unread_file_fills_nothing`: job constructed with `read: false` → unchanged.

- [ ] **Step 2: Run** `php artisan test --filter=DraftPrefillTest` — Expected: FAIL.

- [ ] **Step 3: Implement.** In a transaction: `lockForUpdate` the draft; skip when trashed or `! PaidCheckJourneyService::draftEditable`. Preferred = name contains `PERMOHONAN` (case-insensitive) or `item_code` is the cover-letter item (`borang_pengemukaan`). A field is fillable when (title: `title_provisional`; others: empty) and (no existing source, or existing source not preferred and this one preferred — or the existing source is this same document). Write fields + sources, `version++`, revision row with actor `user_id`, snapshot = the same keys as `PublicPortalController::recordRevision` plus `'prefill' => true`. The job passes the *original* extracted text (reader normalises) — keep a copy before the upper-case normalisation or pass `$text` (already upper-cased; fine).

- [ ] **Step 4: Run** the filter, then `--filter='AutoSortTest|PrecheckJobTest'` — Expected: PASS.

- [ ] **Step 5: Commit** `feat(precheck): fill empty draft details from dropped documents`.

### Task 3: Show, merge and confirm in the form

**Files:**
- Modify: `resources/views/public/drafts/edit.blade.php` (title shows when `prefill_sources['title']` exists; notes under title/address), `resources/views/public/drafts/partials/lighting-types.blade.php` (note), `resources/views/public/drafts/partials/checklist.blade.php` (a `<template data-prefill='@json(...)' data-version="…">` inside the polled section), `app/Http/Controllers/PublicPortalController.php` (`update` clears `prefill_sources`), `public/js/portal.js` (after `replaceWith`, merge), `public/css/indecs.css` (`.prefill-note`).
- Test: `tests/Feature/Precheck/DraftPrefillPagesTest.php`

**Interfaces:**
- Consumes: `prefill_sources`.
- Produces: poll payload `data-prefill` = `{"version": int, "fields": {"title"?: string, "site_address"?: string, "lighting_types"?: list<string>}}`.

- [ ] **Step 1: Write the failing tests:**
  - `test_form_shows_filled_title_with_source_note`: page shows the filled title in the `title` input value and `Diisi daripada SURAT PERMOHONAN.pdf — sila semak.`
  - `test_saving_confirms_and_clears_sources`: PUT update with the current version → `title_provisional` false, `prefill_sources` null, note gone.
  - `test_checklist_fragment_carries_prefill_and_version`: GET `portal.drafts.checklist` contains `data-prefill` with the title and the current version.
  - `test_payment_still_blocked_until_saved`: filled but unsaved draft → `CheckPaymentService::start` refused with `Isi tajuk cadangan dan simpan draf sebelum membayar.`

- [ ] **Step 2: Run** `php artisan test --filter=DraftPrefillPagesTest` — Expected: FAIL.

- [ ] **Step 3: Implement.** portal.js: after a successful poll swap, read `[data-prefill]`; for `title`/`site_address` set `form.elements[name].value` only if it is `''`; for `lighting_types` tick only when none are ticked; then always set the hidden `version` input to the payload version (a box the applicant typed into keeps their value, which is what they mean to save). Insert the note element next to filled boxes.

- [ ] **Step 4: Run** the filter, `node --check public/js/portal.js` — Expected: PASS / no output.

- [ ] **Step 5: Commit** `feat(portal): show and confirm details filled from documents`.

### Task 4: Pilot check and docs

**Files:**
- Modify: `tests/Feature/Precheck/GreenCamourSortTest.php` (assert title starts with `CADANGAN PEMBANGUNAN` and address contains `LOT 22067`)
- Create: `docs/adr/ADR-387-draft-prefill-from-documents.md`; Modify: `docs/adr/README.md`, `docs/runbooks/public-portal.md`

- [ ] **Step 1:** Extend the pilot test; run with `PLAN_REVIEW_GREEN_CAMOUR_PATH` set to the ZIP — Expected: PASS (if OCR of the letter differs, record a ruling and adjust the reader, not the assertion's intent).
- [ ] **Step 2:** Write the ADR (decision, the never-overwrite rule, the version merge) and runbook lines.
- [ ] **Step 3:** Full suite `php artisan test` — Expected: all pass (2 skipped without the pilot path).
- [ ] **Step 4: Commit** `docs: ADR-387 draft prefill from dropped documents`.
