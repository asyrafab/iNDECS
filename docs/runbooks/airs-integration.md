# AIRS review module — operations runbook

AIRS runs inside iNDECS ([ADR-383](../adr/ADR-383-airs-merged-into-indecs.md)); the earlier HTTP connector of ADR-354 is removed. Code: `app/Airs` (engine), `app/Services/PaidCheck` (applicant journey), `app/Services/Kejuruteraan` + `/kejuruteraan` (JKEJ officer workspace). Tables: schema `airs` (engine, officer feedback, field reviews, lesson exports) and `core.check_payments`, `core.paid_check_journeys`, `core.accepted_submissions`, `core.public_holidays`.

## Journey in one paragraph

An LJUP draft (Jabatan Kejuruteraan) declares its lighting types and uses the free local precheck. Payment (or an audited waiver) creates the application and runs check 1; the applicant sees an applicant-safe result and may amend once within 5 working days (extendable once by 5) to run check 2, or submit as-is. The application then hands over to JKEJ with `core.accepted_submissions` written and missing required documents flagged. AIRS never rejects or closes an application; scores are advisory and the threshold is shadow-only.

## Setup

1. Migrate: `php artisan migrate`. On a database that still holds the retired standalone AIRS tables in schema `airs`, remove those tables first (this deletes the old AIRS pilot data — get the owner's approval and back up).
2. Python 3 with `python_worker/requirements.txt` (PyMuPDF, Pillow). Set `AIRS_PYTHON_BINARY` if not `.venv/Scripts/python.exe`.
3. Environment (see `.env.example`):
   - `DB_TIMEZONE=UTC` — the database session must match the UTC application timezone (ADR-314). Rows written earlier under a Malaysia-time session read back 8 hours off.
   - `AIRS_AI_PROVIDER` = `fake` | `openai` | `matrix`; `AIRS_RULE_VERSION`; `AIRS_SCORE_THRESHOLD` (shadow only).
   - MATRIX: `AIRS_MATRIX_ENABLED`, `AIRS_MATRIX_MODEL`, `AIRS_MATRIX_WORKER` (path to `../MATRIX/runtime/cli.mjs`), budgets `AIRS_MATRIX_REQUEST_BUDGET_USD` (0.25) and `AIRS_MATRIX_REVIEW_BUDGET_USD` (5). Check with `php artisan matrix:status`.
   - Fee: `AIRS_CHECK_FEE_SEN` (unset disables payment; amount not yet decided). `AIRS_PAYMENT_DRIVER=staging` is the only driver and is refused in production until the MBSP gateway is integrated.
   - Lessons: `AIRS_MATRIX_LESSONS_PATH` (default `storage/app/airs/lessons`).
4. LJUP checklist: JKEJ supplies it; a `senior_engineering_officer` publishes it with `php artisan catalogue:publish LJUP <file.json> --actor=<email>`.
5. Public holidays for working-day deadlines go in `core.public_holidays`.

## Processes to supervise

- Queue worker for AIRS checks: `php artisan queue:work airs --queue=airs --tries=1 --timeout=960` (connection `airs`, `retry_after` 1000). Checks are never auto-retried by the queue.
- Scheduler (`php artisan schedule:work` or cron) runs:
  - `airs:recover-checks` every minute — re-queues lost checks and retries checks that failed because MATRIX was unavailable (up to 3 attempts).
  - `paid-check:hand-over-expired` every five minutes — hands over journeys whose amendment window ended (check 1 final).

## Operator commands

| Command | Use |
|---|---|
| `php artisan paid-check:stuck` | Journeys whose check never started or failed permanently for over an hour. Rerun a failed check with `CheckService::rerun()` or contact the applicant. |
| `php artisan paid-check:waive <draft> --actor=<email> --reason="<policy ref>"` | Audited fee waiver by an officer holding `kejuruteraan.ljup.fee_waive`. |
| `php artisan airs:export-lessons [--since=]` | Export new officer corrections as de-identified MATRIX candidate lessons (JSONL). Officer comments are free text and may mention names: share files only with the MATRIX evaluation team. |
| `php artisan matrix:status` / `matrix:usage` | MATRIX runtime check and per-check usage (client estimate, not invoice). |

## Officer workspace

`/kejuruteraan` lists LJUP applications for holders of `kejuruteraan.ljup.access` (district scope applies once an application has site/lot records). Officers with `kejuruteraan.ljup.review` comment per finding and review MATRIX-extracted fields on the final, handed-over check; both are attributed and append-only. Officer correction requests, decisions and letters are not part of AIRS.

## Verification

`php artisan test` (uses `indecs_test`, the fake AI provider and a sync `airs` queue). The real-package acceptance test runs with `PLAN_REVIEW_GREEN_CAMOUR_PATH="<folder of the 12 PDFs>" php artisan test --filter=GreenCamourAcceptanceTest`.
