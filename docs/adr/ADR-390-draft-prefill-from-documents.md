# ADR-390: Fill draft details from dropped documents

Date: 2026-11-16

Status: Accepted (owner request; upload-first part 2)

## Decision

After an applicant drops files, the background pre-check fills the draft's provisional title, empty site
address and, for LJUP, empty lighting type from the standard MBSP cover-letter subject
("PERMOHONAN … BAGI CADANGAN … DI ATAS LOT … UNTUK TETUAN …"). Reading stays local and free (ADR-363):
it runs on the text the pre-check job already extracts, and that text is never stored. A lighting type is
filled only when exactly one of solar, konkrit or besi/galvani is named as the pole or light type, outside a list of options ("konkrit atau besi bergalvani" in MBSP's standard conditions names no project type).

## Rules

- Never overwrite the applicant: the title only while provisional, other fields only while empty.
- A file named PERMOHONAN, or placed under the cover-letter item, may replace a value an earlier other file
  filled; nothing else replaces a filled value.
- Each fill bumps the draft version and writes a revision (actor: the draft owner, snapshot marked prefill).
  Locked or removed drafts are never filled.
- Filled fields show "Diisi daripada {fail} — sila semak." and stay unconfirmed (`prefill_sources`) until
  the applicant saves; the provisional-title payment gate applies until then.
- An open form takes filled values into still-empty boxes and the new version (`public/js/draft-prefill.js`),
  so saving merges instead of conflicting.

## Consequences

Letters that do not follow the pattern give no values and the applicant types them as before. Lighting
quantities stay with the paid AIRS review. Verified on the Green Camour pilot scans with the local reader.
