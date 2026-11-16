# Checklist items that hold several files — design

Status: approved 2026-11-16 (short design). Builds on upload-first parts 1–2.

## Intent

LJUP consultants submit "Borang semakan (LJUP 1)" as three files: the form, Syarat-syarat Umum and Syarat-syarat
Khusus. Today an item holds one current file, so two parts stay in "Fail belum dipadankan" or silently become
hidden older versions. Billion Site (pilot 2) showed this, plus two sorting gaps (no "LJUP"/"COVER LETTER" name
keywords) and a text-reading defect (pdftotext output in Latin-1 blanks the text; the worker cannot find
pdftotext and OCRs text PDFs).

## Approaches

- **Chosen (owner): one item, several files** — a catalogue `max_files` per item (default 1; `borang_ljup1` = 5).
- Rejected: split LJUP 1 into three items — needs a JKEJ decision on which parts are required.

## Design

1. Catalogue item key `max_files` (1–10, default 1), stored on `core.document_requirements.max_files`.
2. Checklist items gain `documents` (all current files) and `max_files`; `document` stays the newest current file.
   In a multi-file item every placed file is current (no superseded history). Item label = worst file label
   (Pending > Unreadable > NeedsConfirmation > Attached; Missing when empty).
3. Per-item upload on a multi-file item adds a file ("Tambah fail") until full; identical bytes already in the item
   are refused as a duplicate. Each file has its own confirm and its own Buang. Single-file items unchanged.
4. Auto-sorting adds to a multi-file item while it has room, never displaces from it, and leaves the file in the
   tray when full. Manual "Letak" into a full item is refused with a message.
5. Pre-check job reads every file of a multi-file item (no superseded skip).
6. Paid check copies every current file; the amendment keeps files whose bytes are unchanged, adds new ones and
   withdraws removed ones within a multi-file item (single-file items keep revise/supersede).
7. Keywords: `borang_ljup1` + "LJUP"; `borang_pengemukaan` + "COVER LETTER".
8. Text: pdftotext called with `-enc UTF-8`; extracted text is scrubbed to valid UTF-8. Local `.env` points
   `KAUNTER_PDFTOTEXT_BINARY` at the full path so the worker uses embedded text.

## Not included

Other multi-file items (catalogue data, later, with JKEJ). Officer-side completeness (needs ≥1 current file, unchanged).
