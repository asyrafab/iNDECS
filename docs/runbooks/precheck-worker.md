# Free pre-check worker

ADR-363. The portal's free pre-check runs on its own queue, using local tools
only.

## Tools

| Tool | Used for | Config key / env |
|---|---|---|
| ClamAV `clamscan` | Virus scan before any upload is stored (fails closed) | `INDECS_DOCUMENT_SCANNER_BINARY`, `INDECS_DOCUMENT_SCANNER_DATABASE`, `INDECS_DOCUMENT_SCANNER_CERTIFICATES` |
| poppler `pdftotext` | Embedded PDF text | `KAUNTER_PDFTOTEXT_BINARY` (default `pdftotext`) |
| Ghostscript | Renders image-only PDF pages for OCR | `KAUNTER_GHOSTSCRIPT_BINARY` (default `gswin64c` on Windows, `gs` elsewhere) |
| Tesseract with `msa` and `eng` | OCR of photos and rendered pages | `KAUNTER_TESSERACT_BINARY`, `KAUNTER_TESSERACT_LANGS` (default `msa+eng`) |

The budgets use these env keys:

- `KAUNTER_PRECHECK_OCR_MAX_PAGES` (default 10)
- `KAUNTER_PRECHECK_PROCESS_TIMEOUT` (default 60 seconds per tool run)
- `KAUNTER_PRECHECK_TOTAL_BUDGET` (default 240 seconds per file; keep it
  below the job's 300 s timeout)
- `KAUNTER_PRECHECK_MIN_TEXT_CHARS` (default 30)
- `KAUNTER_PRECHECK_MAX_DOCUMENTS` (default 60 per draft)

Check that Tesseract has the Malay pack: `tesseract --list-langs` must list
`msa`.

**ClamAV is required.** Without it, every applicant and staff upload is refused
with "Pengimbas keselamatan tidak tersedia…". This is deliberate. To develop
locally, install ClamAV (Windows: ClamAV for Windows; run `freshclam` once)
and point `INDECS_DOCUMENT_SCANNER_BINARY` at `clamscan`.

If a text tool is missing, nothing breaks: the item shows "Perlu pengesahan
pemohon" and the PSP confirms it manually. No cloud service is ever called.

## Run the worker

```bash
php artisan queue:work --queue=precheck --timeout=300
```

Keep it separate from the default queue workers, so that public traffic
cannot starve officer work.

## Test the real tools

```bash
php artisan test --group=local-tools
```

These tests skip themselves when a tool is not installed.
