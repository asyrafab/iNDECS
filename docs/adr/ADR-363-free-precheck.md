# ADR-363: Free applicant pre-check

Date: 2026-11-05. Status: Accepted by the programme owner (Kaunter Maya step 3).

## Context

The PSP uploads every required document and gets a free check before paying
for AIRS review ([spec](../superpowers/specs/2026-11-05-public-portal-kaunter-maya-design.md)
§3, §8.6a). The [integration contract](../integration/indecs-airs-contract.md)
§3 limits this stage:

- local text and OCR only;
- no paid or cloud AI;
- preparation labels, never findings;
- bounded resources;
- one active check per draft.

## Decision

- **Particulars.** `core.applicant_drafts` gains `work_type`,
  `building_category`, `storeys`, `floor_area` and `site_conditions`. They are
  validated against `config/kaunter.php` and included in revision snapshots.
  `ApplicantDraft::particulars()` feeds `ChecklistRuleEvaluator` (ADR-362). A
  field left blank is cleared.
- **Check keywords.** Each catalogue item may carry up to 10 `precheck_keywords`,
  stored trimmed and upper-cased. Only system admin sets them, on the
  catalogue page, as one comma-separated field.
- **Uploads.** `core.applicant_draft_documents` holds one row per upload, and
  the latest `sequence` per item is current. `App\Services\Precheck\DraftDocumentService`
  handles uploads:
  1. The draft owner and a verified account are required.
  2. The item must be in the draft's current checklist.
  3. The extension and size limit come from the item's `document_types` row.
  4. The real bytes are content-sniffed with `finfo`.
  5. Re-uploading the same SHA-256 is a no-op.
  6. The per-draft quota (`max_documents_per_draft`) is enforced.
  7. The existing `DocumentScanner` (ClamAV) scans the file before storage
     and fails closed.
  8. The file goes on the private disk at `drafts/{draft}/{code}/{n}.{ext}`.
  9. The pre-check job is dispatched after commit.
- **Extraction.**
  - `App\Contracts\TextExtractor` is bound to `LocalTextExtractor`, which
    tries `pdftotext` first. When there is too little embedded text, it
    renders pages with Ghostscript and runs Tesseract (`msa+eng`). Images go
    straight to Tesseract.
  - Tools run as argument lists, never shell strings, with
    `process_timeout`, and OCR is limited to `ocr_max_pages`.
  - A missing tool, timeout or failure sets `ocr_unavailable`; there is never
    a cloud fallback.
  - The extracted text is never stored. Only the character count, matched
    keywords and flags are kept.
- **Job.** `App\Jobs\PrecheckDraftDocument` runs on the `precheck` queue. It
  is `ShouldBeUnique` per draft, with `tries = 1`, and skips superseded
  uploads.
- **Labels.** `App\Enums\PrecheckLabel` defines:
  - **Belum dilampirkan**: no file.
  - **Sedang disemak**: the check is running (a progress state, not a
    result).
  - **Tidak dapat dibaca**: corrupt, or too little text. The file must be
    replaced and cannot be confirmed.
  - **Perlu pengesahan pemohon**: OCR was unavailable, or keywords are set and
    none matched. The PSP may confirm with "Saya sahkan dokumen ini betul".
  - **Dilampirkan**: otherwise, or after confirmation.

  Every view shows the contract sentence, and never uses pass, fail or score
  wording.
- **Gate.** `DraftChecklist::for($draft)['ready']` is true when every required
  item that applies is Dilampirkan. Step 5 (payment) must use it. Until then
  the pay button is disabled and labelled.
- **Portal.**
  - The draft editor shows "Butiran projek" (Bangunan types) and "Dokumen
    diperlukan". The checklist refreshes by polling while checks run.
  - Uploading is desktop only; below 901px a note replaces the upload
    controls.
  - In Fail saya, drafts with items needing action move to "Perlu tindakan
    anda" with an AMIRA to-do, and rows show "Dokumen x/y".
  - AMIRA's mood is Think while the current draft is being checked, Alert
    when there are to-dos, and Happy when the draft is ready.

## Boundaries

Drafts and their files are not official documents. No application, payment,
accepted submission or AIRS job is created. The owner is the only actor until
groups arrive in step 7.

## Validation

`tests/Feature/Precheck/*`. `LocalTextExtractorTest` (group `local-tools`) runs
the real pdftotext, Ghostscript and Tesseract where they are installed. It was
also checked in a browser on the dev database with real OCR.
