# LJUP upload validation — 18 November 2026

Initial automated validation used code baseline `fdf7437`, with no failures in
those checks. Follow-up browser/worker validation below found and fixed one
validation-return defect (ADR-406).

## Results

| Check | Result |
|---|---|
| Focused PostgreSQL regression run | 243 passed, 897 assertions; real-package test initially skipped because its fixture variable was unset. |
| Green Camour real-package rerun | 1 passed, 23 assertions; 12 PDFs accepted, none rejected. Real local text extraction/OCR completed in approximately 183 seconds. |
| File preservation | All 12 uploads accounted for: 11 placed and the receipt visible in the unmatched tray. |
| Configured ClamAV adapter | Successfully scanned the real LJUP submission form separately. |
| Local published catalogue (read-only inspection) | Nine active items; keywords and five-file limits match all nine checked-in items. |
| JavaScript syntax | `portal.js` and `draft-prefill.js` passed `node --check`. |

The focused run covers loose/ZIP upload routes, unsafe archive and MIME rejection,
duplicate handling, deferred scanning and blocked access to unscanned files,
sorting and manual placement, five-file capacity, removal and replacement,
draft prefill and version conflicts, owner isolation, and the staging paid-check
journey including multi-file copying, amendment and handover. Real-tool tests for
embedded PDF text and image OCR also passed.

## Real-package placement

| Checklist item | Files placed |
|---|---|
| Borang pengemukaan | LJUP-named submission form and cover letter (2) |
| LJUP 1 | LJUP Umum and LJUP Khusus (2) |
| Laporan fotometrik | Lux report (1) |
| Pelan Kebenaran Merancang / Pelan Bangunan | KM plan, building-plan letter and building plan (3) |
| Pelan Jalan dan Parit | Road/drainage letter and plan (2) |
| Pelan Lampu Jalan | Lighting plan (1) |
| Unmatched tray | Payment receipt (1) |

The subject populated the provisional title and the site address included the
expected lot. The source belongs to Borang pengemukaan; the title remains
provisional until the applicant reviews and saves it.

## Reproduce

Run sequentially from the repository root. Feature tests enforce the isolated
PostgreSQL `indecs_test` safety guard.

```powershell
php artisan test --filter='Precheck|PaidCheck|DraftRemoval|DocumentSorter|ExtractedText'
$env:PLAN_REVIEW_GREEN_CAMOUR_PATH='C:/Users/Asyra/Documents/Plan Review/test_subject'
php artisan test --filter=GreenCamourSortTest
node --check public/js/portal.js
node --check public/js/draft-prefill.js
```

Local logs and JUnit reports are retained under `output/ljup-upload-validation.*`
and `output/ljup-real-package-validation.*` (ignored by Git).

## Initial automated-run scope limits

The real-package test uses isolated storage, a synchronous queue and a scanner
test double. The separate ClamAV smoke check verifies the configured scanner on
one real PDF; it does not constitute a full asynchronous-worker test. Paid-check
tests use the staging gateway and a fake AIRS document processor, without paid
model calls. No live payment or production intake was exercised. Browser drag,
dialog focus, responsive layout and polling interactions were not exercised in
this validation. No applicant records or catalogue configuration were changed in
the normal application database.

## Follow-up: real browser and asynchronous worker

Completed 18 November 2026 using the computer-use browser tools, an isolated PHP
server on loopback port 8018, a verified disposable public applicant in
`indecs_test`, isolated private storage, and a separate database queue worker.
AMIRA AI was disabled; no payment or formal submission was performed.

The browser accepted all 12 real Green Camour PDFs through **Pilih fail**, split
into the application's 10-file and 2-file request batches. Before starting the
worker, all 12 document rows had pending scans and all 12 queued jobs had zero
attempts. The upload survived an interrupted validation session without creating
another draft or uploading the package again.

```powershell
php artisan queue:work database --queue=precheck --timeout=300 --tries=40 --sleep=1 --stop-when-empty -v
```

This worker used the real configured ClamAV and local text/OCR tools, with
`QUEUE_CONNECTION=database` and `DB_QUEUE_RETRY_AFTER=360` (longer than its
300-second job timeout). It completed all 12 jobs in approximately 320 seconds
and stopped when the queue emptied. Final state: 12 completed checks, zero
pending scans, zero queued jobs, zero failed jobs, 11 placed files and the receipt
in the unmatched tray. Placement agrees with the table above.

Browser observations:

- Polling updated the checklist without a manual reload and stopped when no
  checks remained pending. The unmatched count fell to one.
- A document dialog and its chosen file remained intact while several worker
  jobs completed. Tab wrapped within the dialog; Escape closed it and restored
  focus to its document link. Dialogs created by polling opened successfully.
- Both Umum/Khusus were visible together under LJUP 1. The submission form and
  cover letter were visible together under Borang pengemukaan.
- An unsaved test note and the applicant's lighting selection survived polling.
  Saving succeeded, confirming the provisional draft rather than losing those
  edits to a stale-version error.
- A duplicate per-item upload was rejected without adding a document. After the
  ADR-406 fix, its error appeared on the draft instead of navigating to JSON.
- A scanned LJUP PDF downloaded successfully and its SHA-256 matched the original.
- No browser console warnings/errors were captured in the final check.

### Defect fixed

AMIRA's local history GET omitted `X-Requested-With`. Laravel treated it as a
navigation and replaced the session's previous URL, so duplicate-upload
validation without a Referer redirected to the history JSON. The history fetch
now identifies itself as AJAX. The same duplicate upload was repeated in the
browser and returned to the draft with `Fail yang sama telah dimuat naik.`
See [ADR-406](../adr/ADR-406-guidance-history-preserves-return-url.md).

Post-fix verification: **32 tests, 209 assertions passed** for
`CounterLayoutTest|PortalPrecheckPagesTest|PublicPortalTest`; JavaScript syntax
and `git diff --check` passed. These ran after browser/worker evidence was saved,
so refreshing `indecs_test` did not interrupt the validation.

### Local evidence and remaining limits

Ignored `output/` artifacts include `ljup-browser-resume.json` (before worker),
`ljup-browser-after-worker.json`, `ljup-browser-worker.log`,
`ljup-browser-regression.log`/`.xml`, and screenshots
`ljup-browser-dialog-preserved.jpg`, `ljup-browser-duplicate-rejected.jpg` and
`ljup-browser-completed.jpg`. Isolated uploaded bytes remain in
`output/ljup-browser-storage/`; temporary validation server and worker are stopped.

Actual file dragging, directory selection, browser ZIP selection, responsive
breakpoints, malware-positive scanning and live payment were not exercised in
this browser pass. ZIP rules and scanner refusal/failure handling remain covered
by the earlier automated tests. The normal application database, existing users
and normal workers were not modified.
