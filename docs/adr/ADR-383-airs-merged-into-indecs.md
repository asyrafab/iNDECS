# ADR-383: AIRS merged into iNDECS as an in-process module

Date: 2026-11-16. Status: accepted by owner. Supersedes in part: [ADR-352](ADR-352-indecs-airs-submission-and-precheck.md) (journey order and AIRS as a separate system) and [ADR-354](ADR-354-airs-review-connector.md) (HTTP service connector). Design: [AIRS module merge](../superpowers/specs/2026-11-13-airs-module-merge-design.md).

## Context

iNDECS took over the public application portal. The standalone AIRS application (Plan Review repository) duplicated identity, submission, billing, letters and officer operations, and reached iNDECS through a service API. The owner decided (13 November 2026) to merge the AIRS code into iNDECS and retire the standalone application.

## Decision

1. **Placement.** The AIRS review engine lives in iNDECS under `app/Airs` with its tables in schema `airs`. `App\Airs\CheckService` is its only entry point. The HTTP connector (`/api/v1/reviews`, `airs:sync`, `integration.airs_*`) is removed. The Plan Review repository is archived as reference; no data is migrated.
2. **Role.** AIRS checks and organises the submitted files, MATRIX reads them, AIRS applies the department checklist and scores, and reports into the iNDECS application.
3. **Journey (LJUP, Jabatan Kejuruteraan, first module).** Draft → free local precheck (checklist fit only, unlimited, no paid AI) → fee payment → application created and AIRS check 1 → applicant sees an applicant-safe result → at most one amendment within 5 working days, extendable once by 5 working days → check 2 (read-only) → automatic handover to JKEJ. Without an amendment, check 1 is final; a window that ends without action hands over check 1.
4. **Fee.** One fee covers at most two completed checks; failed checks never count and are rerun. The tariff is based on twice the measured per-check cost; the amount is not yet set (`AIRS_CHECK_FEE_SEN`). Only a staging payment driver exists until the MBSP gateway is integrated. Authorised JKEJ officers may waive the fee with an audited reason citing the approved policy.
5. **No automatic rejection.** AIRS never closes or rejects an application. Missing required documents are shown to the applicant and flagged to the officer at handover ("Dokumen wajib tidak dilampirkan"); exemptions are an officer decision. The score threshold produces a recorded shadow outcome only; enforcing it needs a later ADR and must not hide findings.
6. **Visibility.** After paying, applicants see rule titles, statuses, standard comments, evidence pages and the score — never AI rationale, confidence, the shadow outcome or officer comments. Officers see all of it.
7. **Officer work.** JKEJ officers comment per finding and review MATRIX-extracted fields; both are attributed and append-only and never overwrite the original AI result. AIRS runs no further checks after handover; officer correction requests, decisions and letters remain iNDECS core work.
8. **Learning.** Officer corrections are exported as de-identified candidate lessons for MATRIX's evaluation process. They are untrusted until evaluated and never retrain models or change rules automatically.

## Consequences

- One identity, one database and one journey; the AIRS public portal, accounts, billing, letters and roster are retired.
- `core.accepted_submissions` is written at handover (after the final check), not before the first check.
- The database session timezone is pinned to UTC (`DB_TIMEZONE`, consistent with ADR-314).
- Open items: real payment gateway, fee amount, waiver policy text, officer decisions/letters for LJUP, confirmation of the LJUP rule pack by JKEJ officers, and the MATRIX representative benchmark before enforcing any threshold.
