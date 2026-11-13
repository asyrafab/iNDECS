# AMIRA chat (ADR-369)

> **AI ecosystem alignment — 9 November 2026:** [Current roles and integration boundaries](../integration/ai-ecosystem.md). AMIRA remains application assistance; MATRIX is the planned document specialist; MINDA is internal intelligence. Existing authority, free-precheck and payment gates remain.


Three things must run besides the web server: the model, the AMIRA backend and an `amira` queue worker.

## 1. Model (LM Studio on this machine for now)

```bash
lms server start --port 1234
lms load qwen/qwen3.5-9b --context-length 8192
```

Check with `lms ps`. To try the larger model, load `qwen/qwen3.8-27b` and set `AMIRA_LLM_MODEL` to it. It is about
20× slower on an 8 GB GPU.

## 2. AMIRA backend (`C:\Users\Asyra\Documents\AMIRA`)

`.env` there:
- `AMIRA_CLIENT_TOKENS='{"indecs":"<64-hex token>"}'`
- `AMIRA_LLM_BASE_URL=http://127.0.0.1:1234/v1`
- `AMIRA_LLM_MODEL=qwen/qwen3.5-9b`
- `AMIRA_LLM_TIMEOUT=120`

Then run `START_AMIRA.cmd`, which serves on 127.0.0.1:8770.

## 3. iNDECS

`.env`:
- `AMIRA_ENABLED=true`
- `AMIRA_SERVICE_URL=http://127.0.0.1:8770`
- `AMIRA_SERVICE_TOKEN=<the same token>`

Then:

```bash
php artisan migrate
php artisan queue:work amira --queue=amira,amira-index --timeout=310
php artisan amira:index-library
php artisan amira:check
```

- `amira:index-library` indexes library PDFs that have no text yet. New uploads are indexed automatically.
- `amira:check` must print the model name and a reply.
- The worker uses the `amira` connection (retry_after 330 s). It handles answers first, then library indexing (`amira-index`). Keep it separate from the `precheck` worker.
- A question unanswered for more than 210 s (no worker running) is closed with "Panduan asas", so the PSP can ask again.
- `amira:prune` runs daily at 03:00 and deletes conversations idle for 12 months.

## When something is down

- **The model server is unreachable, the backend is stopped, or the token is wrong.** The PSP gets a "Panduan
  asas" reply (scripted next step). Nothing is lost.
- **`AMIRA_ENABLED=false`.** The panel goes back to saved local guidance (ADR-368).

## Production

- Change only `AMIRA_LLM_BASE_URL` (HTTPS), `AMIRA_LLM_MODEL` and `AMIRA_LLM_API_KEY` in AMIRA, and
  `AMIRA_SERVICE_URL` (HTTPS) in iNDECS.
- The demo library document "DEMO Kaunter Maya: contoh rujukan" must be retired before real use.
