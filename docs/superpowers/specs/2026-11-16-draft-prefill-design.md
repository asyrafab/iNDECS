# Upload-first part 2: fill draft details from dropped documents — design

Status: approved 2026-11-16 (short design). Builds on [part 1](2026-11-16-upload-first-draft-design.md).

## Intent

An applicant who drops their package should not have to retype what the documents already say. After a drop,
the draft's title, site address and (for LJUP) lighting type are filled from the documents; the applicant checks
and saves. Pre-payment reading stays local and free (ADR-363): no AI, no network.

## Goals / non-goals / anti-goals

- Goal: fill the title, site address and LJUP lighting type from the standard MBSP cover-letter pattern.
- Non-goal: lighting quantities/wattages (no draft field; the paid AIRS review extracts them), files beyond the
  20-file reading budget, non-letter sources other than the LJUP lighting-type mention.
- Anti-goal: overwriting anything the applicant typed; treating a filled value as confirmed; storing extracted text.

## Approaches

- **Chosen — read in the existing background pre-check job.** The job already OCRs each dropped file; the text
  is in hand and is never stored, so details are picked up there.
- Rejected — read on upload: brings back the 30 s request-limit failure fixed in 1efcb0e.
- Rejected — AI extraction: paid/cloud AI is barred before payment.
- Fill vs. suggest: the user chose **fill empty fields** (fewest clicks) over "Guna cadangan" buttons.

## Design

1. `DraftParticularsReader::read(string $text): array{title?: string, site_address?: string, lighting_types?: list<string>}`
   on whitespace-normalised upper-case text.
   - Title: from `CADANGAN` (the first one after `PERMOHONAN`) up to `DI ATAS` / `UNTUK TETUAN` / `PER :`.
   - Address: from `DI ATAS` (dropped) or `LOT` up to `UNTUK` or the sentence end.
   - Both tidied (single spaces, trimmed punctuation) and capped (title 500, address 2000 — the form limits).
   - Lighting type: set only when exactly one of SOLAR / KONKRIT / BESI|GALVANI appears as a pole or light type.
2. Source preference: a file whose name contains PERMOHONAN, or placed under the cover-letter item, beats others.
   A preferred source may replace a value an earlier, non-preferred file filled; nothing replaces applicant input.
3. Fill rules (in the job, under the draft row lock):
   - title only while `title_provisional`; address and lighting type only while empty;
   - record `prefill_sources` (jsonb on the draft): `{field: {document_id, name, preferred}}`;
   - bump `version` and write an `applicant_draft_revisions` row (actor = draft owner, snapshot marked `prefill`).
4. Display: each filled field shows "Diisi daripada {nama fail} — sila semak". The form shows the filled title
   although `title_provisional` is still true.
5. Open form: the checklist poll fragment carries the filled values and the new version; portal.js fills only
   boxes that are still empty and updates the hidden version, so a save merges instead of conflicting.
6. Confirmation: saving the form clears `prefill_sources` and `title_provisional`; until then the payment gate
   "Isi tajuk cadangan dan simpan draf sebelum membayar." still applies.

## Left to the builder

Exact regexes, the lighting-type proximity rule, and the CSS for the "Diisi daripada" note (existing tokens).
