# Upload-first draft (Kaunter Maya) — design

Date: 2026-11-16 · Status: draft for owner approval · Builds on: ADR-363 (free pre-check), contract §3 (no paid AI before payment), 2026-11-05 Kaunter Maya design.

## Intent

Applicants (PSP / consultants) should not have to type the draft details by hand. They drop their documents — several files or one ZIP — onto the new-draft page, or pick them from a folder. AIRS's free local reader sorts each file into the matching checklist item and pre-fills the draft details from the document text. The applicant reviews and corrects; nothing is submitted automatically.

Why: the typed form duplicates what is already printed in the documents (title block, cover letter, lighting schedule). Less typing means fewer abandoned drafts and fewer typos in the official record.

## Scope of this build (owner, 2026-11-16)

Focus on the applicant's file upload only: goals 1, 2 and 5 below (drop zone, folder/ZIP, auto-sorting, unsorted tray, per-item upload kept). Pre-filling draft details (goals 3–4, "Field extractors", `applicant_draft_suggestions`) is part 2, built after this one; the job should keep extracted text local per run so part 2 can plug in. Until part 2, a draft created by a drop keeps the provisional title and the applicant types the title and address as today. Payment is refused while any file is still unsorted (my call): the applicant places it in an item or removes it, so no file is silently left out.

## Goals

1. New-draft page leads with a drop zone ("Seret fail ke sini atau pilih folder") accepting multiple PDF/JPG/PNG files, a whole folder, or one ZIP.
2. Each file is sorted to a checklist item automatically when the local reader is confident; otherwise it waits in an "Belum diisih" tray where the applicant picks the item from a dropdown.
3. Draft details are pre-filled from the documents: title, site address (lot / mukim / district), and for LJUP the lighting types (e.g. 150 W, 180 W found in the lighting schedule).
4. Every pre-filled value is marked "Dibaca daripada dokumen — sila semak" with the source file name, and stays editable.
5. The existing per-item upload on the checklist keeps working (replace one file, add a missing one).

## Non-goals

- No MATRIX, OpenAI or any paid/cloud call before payment (owner decision: approach A). MATRIX still reads everything properly in the paid check.
- No auto-submission, no auto-payment, no change to the paid-check journey, amendment rule or hand-over.
- No guessing of the application type from documents in this release: the applicant picks the type first (one click), because the checklist and sorting depend on it.
- No storage of the full extracted text (existing ADR-363 rule) — only the suggested field values and the matched keywords.

## Anti-goals (a failure even if it works)

- A wrong guess silently becomes the official title/address. Pre-filled values must be visibly marked and confirmed by the applicant saving the draft.
- A file is silently dropped or placed in the wrong item with no way to move it.
- The free check gets slow or expensive enough that people avoid it (bounded by the existing local time budget).
- A ZIP becomes an attack path (path traversal, zip bomb, nested archives, disguised files).

## Constraints

- Local tools only: pdftotext, Ghostscript, Tesseract (existing `LocalTextExtractor`, total budget `kaunter.precheck.total_budget`, OCR page cap). Missing tools → manual sorting and manual entry, never an error that blocks the draft.
- Same upload safety as today per file: extension + sniffed MIME, per-item size limit, virus scan (fail closed), SHA-256 de-duplication, `max_documents_per_draft`.
- ZIP: reuse the AIRS `ArchiveExtractor`/`FileValidator` rules (no traversal, entry count and uncompressed-size caps, no nested archives, only allowed types inside). Original ZIP is not kept; each accepted entry becomes a normal draft document.
- Draft locking unchanged: no drop zone when `draftEditable()` is false.
- Malay UI, existing Blade + `indecs.js`, no new front-end framework.

## Approaches considered

- **A. Local reading before payment (chosen by owner).** File-name + keyword sorting and rule-based field extraction from local text/OCR; applicant confirms. Free and within contract §3; accuracy limited on scanned drawings.
- B. MATRIX reads before payment. Better extraction, but costs on every draft (including never-paid ones) and breaks contract §3. Rejected by owner.

## Design

### Flow

1. **Create page.** Applicant picks application type (LJUP shown first while it is the only paid type). The drop zone appears below. Title/address/notes fields move under a collapsed "Butiran draf" section, pre-filled later.
2. **Drop.** Files (or a folder via `webkitdirectory`, or one ZIP) upload in one request (or a few batched requests for large sets). The server creates the draft immediately with a provisional title ("Draf baharu — <tarikh>") so files have somewhere to live, then stores each accepted file as an *unsorted* draft document and queues the existing local pre-check job.
3. **Sort + read (queued).** The pre-check job, after extracting text as today, additionally:
   - scores the file against each checklist item (file-name tokens + item `precheck_keywords`); best item wins if its score clears a threshold and beats the runner-up by a margin; otherwise it stays unsorted;
   - runs field extractors over the text and records suggestions with the source document.
4. **Review.** The draft page (existing poller) shows: sorted files in their checklist items, the "Belum diisih" tray with a dropdown per file, and the "Butiran draf" section with suggestions marked "Dibaca daripada dokumen". Rejected files (wrong type, too big, infected, unreadable ZIP entry) are listed with the reason.
5. **Confirm.** Applicant edits and saves the details as today (same validation, revision history). Suggestions never overwrite a value the applicant already typed.

### Sorting rules (my call, tunable in config)

- Every item in the published catalogue for the type is a candidate (particulars may not be filled yet). The checklist still shows only applicable items; a file sitting in an item that stops applying after the particulars change is shown back in the "Belum diisih" tray, not deleted. Sorting runs once per file in the job; it is not re-run on save.
- A file that is still unsorted is checked against the loosest limits of the type's catalogue (largest item size limit, union of allowed file types). Moving it into an item re-checks that item's limits; if they fail, the move is refused with the reason and the file stays in the tray.
- Per-draft reading budget: the first `kaunter.precheck.read_files_per_drop` files (default 20) get full text/OCR reading; beyond that, sorting uses the file name only and the files are marked "Perlu pengesahan pemohon", so a large drop settles in minutes, not hours.
- One file per item, as today: if two files score best for the same item, the higher score is placed and the other goes to the tray.
- An item that already has a file the applicant placed manually is not replaced by auto-sorting.
- LJUP catalogue gets `precheck_keywords` per item (e.g. lighting plan: "LAMPU JALAN", "JADUAL LAMPU"; photometric: "FOTOMETRIK", "LUX", "PHOTOMETRIC"), published as a new catalogue version and flagged for JKEJ confirmation like the rest of the checklist.

### Field extractors (my call)

Regex/keyword based, Malay + English, run on the bounded local text:
- **Title:** line starting "CADANGAN" / "PROPOSED" in cover letter or drawing title block; longest such line wins; trimmed to 500 chars.
- **Site address:** "LOT …", "MUKIM …", "DAERAH SEBERANG PERAI (UTARA|TENGAH|SELATAN)", "SPU/SPT/SPS" patterns combined into one line.
- **LJUP lighting types:** the pole/system types in `config('airs.lighting_types')` (BESI, KONKRIT, SOLAR), detected by keywords: BESI ← "TIANG BESI", "GALVANI", "FEEDER PILLAR"; KONKRIT ← "TIANG KONKRIT", "TNB"; SOLAR ← "SOLAR", "PANEL SURIA". Wattages are not a draft field and are left to MATRIX in the paid check. *(Builder-check correction: the field holds pole types, not wattages.)*
- Each suggestion stores value, source document id, and a confidence band (tinggi/rendah); low-confidence suggestions are shown but not pre-filled into the input.
- Suggestions are never written to the draft by the server (no version bump, no revision row the applicant did not make). The page renders a high-confidence suggestion as the input's value only when the saved value is empty or the provisional title; the draft changes only when the applicant presses "Simpan draf".

### Data

- `applicant_draft_documents.item_code` becomes nullable (null = unsorted); add `sort_source` (`auto`|`manual`) and `sort_score`.
- New `core.applicant_draft_suggestions` (draft_id, field, value, source_document_id, confidence, created_at); latest per field wins; no full text.
- Drafts created by drop carry `title_provisional = true` until the applicant saves a title; payment is refused while the title is provisional.

### Errors

- Local tools missing → files stay unsorted, no suggestions, banner "Bacaan automatik tidak tersedia — sila isih fail dan isi butiran sendiri."
- ZIP rejected as a whole only when it is unsafe; otherwise bad entries are skipped and listed.

## Left to the builder

Upload batching size, exact score threshold/margin defaults, poller payload shape, UI component details within the existing style, and test fixtures (use the Green Camour package for an end-to-end sort/extract test).
