# AMIRA conversation on Qwen Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A PSP can chat with AMIRA in the Kaunter Maya panel. She answers in Bahasa Melayu from the draft's checklist and the department library, cites what she used, and proposes project particulars that the PSP confirms. A local Qwen model on LM Studio runs her, through the AMIRA backend.

**Architecture:** iNDECS owns identity, records, conversation storage, limits and audit. It queues each question, builds a minimised context bundle (draft particulars, checklist labels, library excerpts) and calls the AMIRA backend server-side. AMIRA (`C:\Users\Asyra\Documents\AMIRA`, stateless, no database) owns the persona, prompt, guard rails and the `LocalAiProvider` that calls the OpenAI-compatible LM Studio endpoint. It returns structured JSON (reply, cited source ids, optional particulars proposal). iNDECS validates the result, stores it, and shows it through polling. When anything fails, AMIRA's answer is replaced with deterministic guidance.

**Tech Stack:**
- iNDECS: Laravel 12, PHP 8.3, PostgreSQL (`core` schema, full-text search with `simple` config), Blade, plain JS, the database queue (`amira` queue).
- AMIRA: dependency-free PHP 8.3, with tests in `tests/run.php`.
- LM Studio OpenAI-compatible API, model `qwen/qwen3.5-9b` (configurable).

**Spec:** `docs/superpowers/specs/2026-11-05-public-portal-kaunter-maya-design.md` (§1, §4.1–4.4, §6, §7 ADR-352/353, §8.1, §8.6, §9 step 4).

## Decisions settled for this step (owner, 2026-11-06)

- **Development model:** `qwen/qwen3.5-9b`. Measured on this PC: 4–7 s per reply, correct grounded answers, says it is unsure when there is no source, and refuses to predict outcomes. `qwen/qwen3.8-27b` (1–1.5 min per reply) remains a configuration swap. Reasoning is disabled (`reasoning_effort: none`).
- **Daily limit:** 30 PSP messages per user per calendar day (Asia/Kuala_Lumpur).
- **Retention:** conversations are deleted 12 months after their last message.
- **Implementer's calls** (recorded in ADR-369):
  - **Conversation storage lives in iNDECS, not AMIRA.** The spec says the conversation "lives in the AMIRA backend", meaning its logic. ADR-353 and AMIRA's AGENTS.md forbid a database in AMIRA, and iNDECS already owns identity and audit. AMIRA stays stateless.
  - **Context is pushed, not pulled.** iNDECS sends AMIRA only what the answer needs. It never sends name, email, IC, draft title, address, notes or document content. The subject is an HMAC of the user id. AMIRA keeps `record_access: false`.
  - **AMIRA never changes state.** A particulars proposal is a card. "Guna butiran ini" only fills the form (draft page) or opens a prefilled new-draft form (home), and the PSP saves it. The model's extraction was measured to be unreliable (for example, it read "kedai baru" as `tambahan`), so confirmation is mandatory.
  - **Library grounding uses PostgreSQL full-text search** (`simple` config) over text chunks from `pdftotext`. pgvector is not installed. There is no OCR for library documents.
  - **Asynchronous replies.** Questions go to the `amira` queue, and the panel polls the thread. AMIRA's bubble is Think while a reply is pending.
  - **AMIRA directory under git.** It is not a git repository today. Task 1 runs `git init` and commits the existing baseline before any change.

## Global Constraints

- Tokens only from `public/css/tokens.css`, with no hard-coded colour, font or spacing. Bahasa Melayu throughout, Lucide icons, dark mode and reduced motion supported.
- "AMIRA membantu. Pegawai memutuskan." stays in the footer. AMIRA never states an outcome (§6 anti-goal, §8.1).
- Every AI reply is labelled as AI (eyebrow "Jawapan AI"). Fallback replies are labelled "Panduan asas".
- The AMIRA service is called only server-side. The token never reaches the browser and is never logged. Neither system logs message bodies.
- Free pre-check (ADR-363) stays model-free. Chat is a separate feature.
- Switching to the production server is configuration only: `AMIRA_LLM_BASE_URL`, `AMIRA_LLM_MODEL`, `AMIRA_LLM_API_KEY` in AMIRA; `AMIRA_SERVICE_URL`, `AMIRA_SERVICE_TOKEN` in iNDECS.
- Limits:
  - message ≤ 1000 characters;
  - 30 PSP messages a day;
  - one pending reply per user;
  - route throttle 10/min;
  - history sent = last 6 messages;
  - library excerpts sent ≤ 4, each ≤ 1200 characters;
  - AMIRA request ≤ 32 KiB;
  - reply ≤ 1500 characters.
- Timeouts: LLM 120 s (AMIRA), HTTP 150 s (iNDECS client), job 180 s, worker `--timeout=200`.

## Review Focus

1. **The model returns malformed JSON, prose, `<think>` blocks or an over-long reply.** AMIRA must return `MODEL_OUTPUT_INVALID`, and iNDECS must store a fallback reply, never raw model text. Tests: AMIRA `invalid model output` checks; iNDECS `AnswerAmiraMessageTest::invalid_service_payload_stores_fallback`.
2. **A cited source id the context never offered** (hallucinated citation) must be dropped, never linked. Tests: AMIRA `unknown source ids are dropped`; iNDECS `citations_outside_context_are_dropped`.
3. **The proposal contains values outside `config/kaunter.php`, or a type the user cannot apply for.** Those keys must be dropped before the card is shown. Test: `ProposalSanitiserTest`.
4. **A worker crash or LM Studio down mid-reply.** The thread must not stay "AMIRA sedang menaip…" forever: `failed()` writes a fallback. Test: `failed_job_writes_fallback_reply`.
5. **Another PSP's draft id in the chat URL, or a second question while one is pending.** Expect 404, and 409 "Tunggu AMIRA selesai menjawab". Tests: `ChatAccessTest::foreign_draft_is_404`, `second_question_while_pending_is_409`.

---

### Task 1: AMIRA backend — LocalAiProvider and conversation endpoint

**Files (all under `C:\Users\Asyra\Documents\AMIRA`):**
- Create: `src/LlmTransport.php`, `src/HttpLlmTransport.php`, `src/LocalAiProvider.php`, `src/Conversation.php`, `resources/amira-persona.ms-MY.txt`
- Modify: `bootstrap.php`, `public/index.php`, `src/Application.php`, `.env.example`, `README.md`, `docs/ARCHITECTURE.md`, `docs/BACKLOG.md`, `AGENTS.md`, `tests/run.php`, `tests/http_smoke.py`

**Interfaces:**
- Produces, as the HTTP contract consumed by Task 2 (`AmiraClient`, `AmiraReply`) and Task 4 (`ContextBuilder`):
  - `POST /api/v1/conversation`, JSON, Bearer client token, ≤ 32768 bytes. Exact request keys:
    ```json
    {"subject":"<64 hex>","locale":"ms-MY","question":"<=1000 chars",
     "history":[{"role":"psp|amira","text":"<=1500"}],            // <= 6
     "context":{"page":"home|draft","application_type":{"id":"PB","name":"..."}|null,
       "particulars":{"work_type":..,"building_category":..,"storeys":..,"floor_area":..,"site_conditions":[..]}|null,
       "checklist":[{"name":"..","required":true,"label":"Dilampirkan","guidance":".."}],   // <= 50
       "options":{"application_types":{"PB":"Pelan Bangunan Penuh"},"work_types":{..},"building_categories":{..},"site_conditions":{..}},
       "sources":[{"id":"S1","title":"..","excerpt":"<=1200"}]}}  // <= 4
    ```
  - 200 response: `{"data":{"reply":string,"source_ids":["S1"],"proposal":{"application_type":?,"work_type":?,"building_category":?,"storeys":?,"floor_area":?,"site_conditions":[]?}|null,"mentions_outcome":bool,"model":string,"ai_generated":true,"official_decision":false}}`
  - Errors: 401, 405, 413 `REQUEST_TOO_LARGE`, 415, 422 `INVALID_CONVERSATION_REQUEST`, 503 `MODEL_NOT_CONFIGURED`, 502 `MODEL_UNAVAILABLE`, 502 `MODEL_OUTPUT_INVALID`.
  - `GET /api/v1/capabilities`: `free_text_conversation` and `model_inference` are true only when the LLM is configured; `record_access` and `state_changes` are always false.
- `interface LlmTransport { public function chat(array $payload): array; }` returns decoded OpenAI JSON and throws `RuntimeException` on network or HTTP failure.
- `LocalAiProvider::__construct(LlmTransport $transport, string $model, ?string $apiKey)`, then `complete(string $system, array $messages, array $jsonSchema): array`. It sends `temperature 0.3`, `max_tokens 700`, `reasoning_effort "none"` and `response_format json_schema strict`. It strips `<think>…</think>` and returns the decoded object, or throws `UnexpectedValueException` (invalid output) or `RuntimeException` (unavailable).
- `Conversation::answer(array $request): array` validates and calls the provider. Post-processing:
  - drop `source_ids` not in `context.sources[*].id`;
  - drop proposal keys whose values are not in `context.options`;
  - reply over 1500 characters or empty → `UnexpectedValueException`;
  - strip any HTML tags;
  - `mentions_outcome` is true when the reply matches `/\b(lulus|diluluskan|ditolak|tolak|gagal)\b/iu`.

- [ ] **Step 1: Put AMIRA under git.** Run `git init` in the AMIRA directory and commit the existing files as `chore: baseline AMIRA backend before conversation`, confirming `.env` is ignored (add `.gitignore` with `.env` if missing).
- [ ] **Step 2: Write the failing checks in `tests/run.php`.** Use a `FakeTransport` (an array of queued responses or exceptions):
  - capabilities report `free_text_conversation === false` without LLM config, and `true` with it;
  - a valid request returns 200 with the reply, and the transport received `reasoning_effort === 'none'` and a `response_format.json_schema`;
  - the system prompt contains the persona file and the `KONTEKS` block with source ids;
  - unknown source ids are dropped;
  - a proposal value outside the options is dropped;
  - invalid model output → 502 `MODEL_OUTPUT_INVALID` for each of: non-JSON, a `<think>` block only, a 1501-character reply, and a missing `reply`;
  - transport exception → 502 `MODEL_UNAVAILABLE`;
  - LLM not configured → 503 `MODEL_NOT_CONFIGURED`;
  - 422 on extra top-level keys, a question over 1000 characters, 7 history items, 5 sources, 51 checklist items, and a non-hex subject;
  - a 32769-byte body → 413;
  - `mentions_outcome` is true for "Permohonan anda akan lulus".
- [ ] **Step 3: Run `php tests/run.php`.** Expected: FAIL (class `Conversation` not found).
- [ ] **Step 4: Implement it.**
  - `bootstrap.php` loads an allowlist: `AMIRA_CLIENT_TOKENS`, `AMIRA_LLM_BASE_URL`, `AMIRA_LLM_MODEL`, `AMIRA_LLM_API_KEY`, `AMIRA_LLM_TIMEOUT` (default 120).
  - `HttpLlmTransport` uses `file_get_contents` with an `http` stream context: POST `{base}/chat/completions`, `timeout`, `ignore_errors`. It accepts only `http` on loopback or `https`.
  - The size limit is per path: guidance stays 2048, conversation is 32768. `public/index.php` reads up to the larger limit, and `Application` enforces per path.
  - The persona file says:
    - who AMIRA is (casual, professional, §4.4);
    - use only KONTEKS facts and say "saya tidak pasti" otherwise, pointing to Jabatan Bangunan;
    - never predict or state an outcome ("pegawai yang memutuskan");
    - state costs only when given (none are given in this step);
    - cite source ids used;
    - propose particulars only when the PSP describes a project, using the option codes;
    - reply in plain text, with "-" lists allowed.
- [ ] **Step 5: Run `php tests/run.php` and `py tests/http_smoke.py`.** Expected: both pass. Add to the smoke test a 413 check on a 32769-byte conversation body.
- [ ] **Step 6: Update the docs.** README API table, capabilities, run instructions with LM Studio, ARCHITECTURE "Conversation" section, BACKLOG ticks, AGENTS lines ("conversation is stateless; context pushed by the owning system"), `.env.example` keys with `AMIRA_LLM_BASE_URL=http://127.0.0.1:1234/v1` and `AMIRA_LLM_MODEL=qwen/qwen3.5-9b`. Then commit in AMIRA: `feat: stateless grounded conversation on a local OpenAI-compatible model`.

### Task 2: iNDECS — conversation storage and AMIRA client

**Files:**
- Create: `config/amira.php`, `database/migrations/2026_11_06_000100_create_amira_conversations.php`, `app/Models/AmiraConversation.php`, `app/Models/AmiraMessage.php`, `app/Services/Amira/AmiraClient.php`, `app/Services/Amira/AmiraReply.php`
- Modify: `.env.example`, `phpunit.xml` (set `AMIRA_ENABLED=false` and a 64-character test token)
- Test: `tests/Feature/Amira/AmiraClientTest.php`, `tests/Feature/Amira/ConversationStorageTest.php`

**Interfaces:**
- `config('amira')` keys:
  - `enabled` (env `AMIRA_ENABLED`, default false), `url`, `token`;
  - `timeout` 150, `daily_limit` 30, `max_chars` 1000, `history` 6, `sources` 4, `excerpt_chars` 1200, `retention_months` 12, `poll_minutes` 5;
  - `stopwords` (list: `saya, nak, apa, yang, dan, untuk, ini, itu, ke, di, dengan, boleh, ada, tak, tidak, perlu, macam, mana`).
- `core.amira_conversations`:
  - `id` ulid PK, `user_id` FK users (cascade delete), `draft_id` ulid nullable FK `core.applicant_drafts` (cascade delete), `last_message_at` timestamp, timestamps;
  - unique `(user_id, draft_id)`, plus a partial unique index on `(user_id) WHERE draft_id IS NULL`.
- `core.amira_messages`:
  - `id` bigint, `conversation_id` FK (cascade delete), `role` (`psp` or `amira`), `body` text, `sources` jsonb nullable (`[{library_document_id, version, title}]`), `proposal` jsonb nullable;
  - `fallback` bool default false, `mentions_outcome` bool default false, `model` string(120) nullable, `latency_ms` int nullable, `created_at`.
  - A trigger function `core.prevent_amira_message_update()` raises SQLSTATE 42501 on UPDATE only; DELETE is allowed for retention.
- `AmiraConversation::for(User $user, ?ApplicantDraft $draft): self` is firstOrCreate. `messages()` is a HasMany ordered by id. `pending(): bool` is true when the latest message's role is `psp`.
- `AmiraClient::ask(array $request): AmiraReply` POSTs `/api/v1/conversation`, with the same URL and loopback rules as `AirsClient::request()`. It throws `RuntimeException` on misconfiguration, a non-2xx response or a shape mismatch.
- `final readonly class AmiraReply { string $reply; list<string> $sourceIds; ?array $proposal; bool $mentionsOutcome; string $model; }` with `static fromPayload(array): self`, which validates types strictly.

- [ ] **Step 1: Write failing tests.**
  - `ConversationStorageTest`:
    - `for()` returns the same row twice;
    - a home conversation and a draft conversation are distinct;
    - updating a message throws a `QueryException` with code 42501;
    - deleting a conversation removes its messages;
    - `pending()` toggles when a `psp` message is followed by an `amira` message.
  - `AmiraClientTest` with `Http::fake`:
    - sends a bearer token and the exact top-level keys;
    - a 502 throws;
    - a payload with `source_ids` that is not an array throws;
    - a disabled config throws;
    - an `http://` non-loopback URL throws.
- [ ] **Step 2: Run `php artisan test tests/Feature/Amira`.** Expected: FAIL (missing migration and classes).
- [ ] **Step 3: Implement the migration, models, config, client and DTO.**
- [ ] **Step 4: Run `php artisan test tests/Feature/Amira`.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(amira): conversation storage and server-side AMIRA client`.

### Task 3: iNDECS — library text index and retrieval

**Files:**
- Create: `database/migrations/2026_11_06_000200_create_library_chunks.php`, `app/Jobs/IndexLibraryVersion.php`, `app/Services/Amira/LibraryRetriever.php`, `app/Console/Commands/IndexLibrary.php`
- Modify: `app/Services/Library/LibraryService.php` (dispatch `IndexLibraryVersion` afterCommit in `create` and `addVersion`)
- Test: `tests/Feature/Amira/LibraryRetrieverTest.php`, `tests/Feature/Amira/IndexLibraryVersionTest.php`

**Interfaces:**
- `core.library_chunks`: `id`, `version_id` FK `core.library_document_versions` (cascade delete), `document_id` ulid, `chunk_no` int, `page` int nullable, `body` text, and `tsv` tsvector generated always as `to_tsvector('simple', body)` stored, with a GIN index. Unique `(version_id, chunk_no)`.
- `IndexLibraryVersion` (constructed with `int $versionId`, queue `amira`): runs `pdftotext -layout` page by page (binary `config('kaunter.precheck.pdftotext')`, `process_timeout`), splits into chunks of about 1000 characters on paragraph boundaries, and replaces that version's chunks. If pdftotext is missing or fails, the version keeps 0 chunks and a warning is logged without file text.
- `php artisan amira:index-library` dispatches the job for every active document's current version that has no chunks.
- `LibraryRetriever::search(string $question, ?string $applicationTypeId, int $limit): list<array{id: string, library_document_id: string, version: int, title: string, excerpt: string}>`:
  - the ids it returns are `S1`…`Sn`, in rank order;
  - only active, public documents on their current version are searched;
  - when a type is given, only documents linked to that type (`core.library_document_application_types`) are searched;
  - the OR query is built from lower-cased words of at least 3 letters, excluding `config('amira.stopwords')`, and matches nothing when no word remains;
  - results are ranked by `ts_rank`;
  - each excerpt is cut to `excerpt_chars`.

- [ ] **Step 1: Write failing tests.**
  - `LibraryRetrieverTest` (seeds chunks directly):
    - a question containing "anjakan" returns the chunk containing "anjakan" as `S1`;
    - internal, retired and old-version chunks are never returned;
    - type filtering works;
    - a stopword-only question returns `[]`;
    - the limit is respected;
    - the excerpt length is at most 1200.
  - `IndexLibraryVersionTest` (`@group local-tools`, skips when pdftotext is missing): indexing a two-page Ghostscript-made PDF creates chunks with `page` 1 and 2. Also, a missing binary (config pointed at `nonexistent-pdftotext`) leaves 0 chunks without throwing.
- [ ] **Step 2: Run `php artisan test tests/Feature/Amira`.** Expected: FAIL.
- [ ] **Step 3: Implement the migration, job, retriever, command and the `LibraryService` dispatch.**
- [ ] **Step 4: Run `php artisan test tests/Feature/Amira tests/Feature/Library`.** Expected: PASS, with the existing library tests unchanged.
- [ ] **Step 5: Commit** `feat(amira): index public library text for grounded answers`.

### Task 4: iNDECS — asking, answering, fallback and limits

**Files:**
- Create: `app/Services/Amira/ConversationService.php`, `app/Services/Amira/ContextBuilder.php`, `app/Services/Amira/ProposalSanitiser.php`, `app/Services/Amira/FallbackGuidance.php`, `app/Jobs/AnswerAmiraMessage.php`
- Test: `tests/Feature/Amira/ConversationServiceTest.php`, `tests/Feature/Amira/AnswerAmiraMessageTest.php`, `tests/Feature/Amira/ProposalSanitiserTest.php`

**Interfaces:**
- `ConversationService::ask(User $user, ?ApplicantDraft $draft, string $text): AmiraConversation`:
  - requires a verified email;
  - trims the text;
  - rejects (ValidationException on `question`) an empty text or one over `max_chars`;
  - takes a user row lock;
  - a reply already pending → `ConflictHttpException('Tunggu AMIRA selesai menjawab.')`;
  - today's PSP messages ≥ `daily_limit` → ValidationException "Had 30 soalan sehari telah dicapai. Cuba lagi esok.";
  - inserts the `psp` message, sets `last_message_at`, and dispatches `AnswerAmiraMessage` afterCommit.
  - When `config('amira.enabled')` is false it still stores the message and dispatches; the job then writes the fallback.
- `ContextBuilder::for(AmiraConversation $c, string $question): array` builds the Task 1 request:
  - `subject` = `hash_hmac('sha256', (string) $user->id, config('app.key'))`;
  - `history` = the 6 messages before the question;
  - `context.page` is `draft` or `home`;
  - for a draft: type, particulars, and a checklist from `DraftChecklist::for()` (name, required, label value, guidance);
  - `options` come from `config('kaunter')` plus the user-applicable types (the same list as `PublicPortalController::types()`; extract that query to `ApplicationType::scopePortal()`);
  - `sources` = `LibraryRetriever::search($question, $draft?->application_type_id, config('amira.sources'))`, mapped to `{id, title, excerpt}`.
  - It never includes the name, email, title, site address, notes or document text.
- `ProposalSanitiser::clean(?array $proposal, array $options): ?array` keeps only allowed keys whose values are in the options; `storeys` must be an integer 1–100 and `floor_area` a number from 1 to 1000000. It returns null when nothing remains.
- `AnswerAmiraMessage`:
  - constructed with `string $conversationId`;
  - queue `amira`, `$tries = 1`, `$timeout = 180`;
  - `WithoutOverlapping($conversationId)`;
  - skips when the conversation is not pending;
  - calls `AmiraClient::ask()`, maps `source_ids` back to `{library_document_id, version, title}` (unknown ids dropped), and sanitises the proposal;
  - inserts an `amira` message with `model` and `latency_ms`;
  - on `RuntimeException` (including config disabled) it inserts `FallbackGuidance::for($conversation)` with `fallback=true`;
  - `failed()` does the same if the conversation is still pending.
- `FallbackGuidance::for(AmiraConversation $c): string` returns "Maaf, saya tidak dapat berbual sekarang." followed by:
  - for a draft: the next action from its checklist (the first item where `needsAction()`, or "Semua dokumen wajib telah dilampirkan.");
  - for home: the first PortalBoard todo, or "Tiada tindakan diperlukan hari ini.";
  - then " Untuk soalan teknikal, hubungi Jabatan Bangunan MBSP."

- [ ] **Step 1: Write failing tests.**
  - `ConversationServiceTest`:
    - stores the message and pushes the job (`Queue::fake`);
    - a second ask while pending gives 409;
    - the 31st message today is rejected, and a message from yesterday does not count (`travelTo`);
    - 1001 characters is rejected;
    - an unverified user gets 403;
    - another user's draft gets 404 (`ModelNotFound`).
  - `AnswerAmiraMessageTest` with `Http::fake`:
    - a valid reply is stored with sources mapped to library ids;
    - `citations_outside_context_are_dropped`;
    - `invalid_service_payload_stores_fallback`;
    - a 502 stores the fallback;
    - disabled config stores the fallback without an HTTP call (`Http::assertNothingSent`);
    - `failed_job_writes_fallback_reply`;
    - the request body never contains the user's email, name, draft title or site address (assert on the recorded request);
    - a non-pending conversation is a no-op.
  - `ProposalSanitiserTest`: drops an unknown `work_type`, a disallowed type, `storeys` 0 and 101, and site conditions outside the options; keeps valid values; returns null when everything is dropped.
- [ ] **Step 2: Run `php artisan test tests/Feature/Amira`.** Expected: FAIL.
- [ ] **Step 3: Implement it.**
- [ ] **Step 4: Run `php artisan test tests/Feature/Amira`.** Expected: PASS.
- [ ] **Step 5: Commit** `feat(amira): queued grounded answers with limits and fallback`.

### Task 5: iNDECS — chat in the Kaunter Maya panel

**Files:**
- Create: `app/Http/Controllers/AmiraChatController.php`, `resources/views/partials/amira-thread.blade.php`, `public/js/amira-chat.js`
- Modify:
  - `routes/public.php`;
  - `resources/views/partials/amira-counter.blade.php`;
  - `resources/views/components/portal-counter.blade.php` (passes `chat` data: thread URL, post URL, draft id);
  - the layout script include;
  - `public/css/app.css` (or the portal stylesheet in use) for the thread, message and card styles, tokens only;
  - `app/Http/Controllers/PublicPortalController.php` (`create()` reads `prefill[application_type]`, `prefill[work_type]`, `prefill[building_category]`, `prefill[storeys]`, `prefill[floor_area]` and `prefill[site_conditions][]`, cleaned by `ProposalSanitiser` against the portal options; `title` is left empty; an existing `?type=` still works);
  - `app/Models/ApplicationType.php` (`scopePortal`).
- Test: `tests/Feature/Amira/ChatAccessTest.php`, `tests/Feature/Amira/ChatPanelTest.php`

**Interfaces:**
- Routes inside the `public.access` group:
  - `GET /portal/amira/thread` and `GET /portal/drafts/{draft}/amira/thread` → `portal.amira.thread` and `portal.drafts.amira.thread` (throttle 60/min) return the thread partial HTML.
  - `POST` to the same paths without `/thread` (`portal.amira.ask`, `portal.drafts.amira.ask`, throttle 10/min) accept `question` and return the thread partial (201), 409 with JSON `{message}`, or 422.
- The thread partial is `<div data-amira-thread data-pending="true|false" data-thread-url="…">`. It holds:
  - the messages, oldest first;
  - PSP messages right-aligned;
  - AMIRA messages with an eyebrow:
    - "Jawapan AI" normally;
    - "Panduan asas" on fallback;
    - when `mentions_outcome`: the fixed line "Keputusan dibuat oleh pegawai MBSP, bukan AMIRA.";
  - sources as "Rujukan: <title> (versi n)" linking `portal.library.download`;
  - a proposal card listing labelled values plus a button `data-amira-apply='{json}'` labelled "Guna butiran ini";
  - while pending, a typing row "AMIRA sedang menaip…" with `aria-live="polite"`;
  - bodies escaped with `e()`, with newlines turned into `<br>`.
- Counter partial changes:
  - when `config('amira.enabled')`, the composer label becomes "Tanya AMIRA";
  - the "Perbualan AI belum tersedia" badge is removed and replaced by the note "Perbualan disimpan 12 bulan untuk audit. Jangan kongsi maklumat peribadi sensitif.";
  - the form gets `data-amira-chat` with the URLs and `maxlength="1000"`;
  - when disabled, today's local-guidance behaviour is unchanged.
- `amira-chat.js`:
  - submits via fetch (CSRF header) and swaps the thread;
  - sets the bubble mood (`window.AmiraBubble.setMood('think')` while pending);
  - `setSpeaking(true)` for 2.5 s when a new AMIRA message arrives (skipped under reduced motion);
  - polls `data-thread-url` every 3 s while `data-pending="true"`, stopping after `poll_minutes`;
  - shows 409/422 messages inline;
  - "Guna butiran ini":
    - on a draft page, fills the matching form fields (`select[name=work_type]` etc., checkboxes `site_conditions[]`), scrolls to "Butiran projek" and focuses "Simpan draf";
    - on home, navigates to `portal.drafts.create` with the prefill query.
- On a phone the thread lives in the existing sheet; nothing else changes.

- [ ] **Step 1: Write failing tests.**
  - `ChatAccessTest`:
    - `foreign_draft_is_404` for both thread and ask;
    - a guest is redirected to login;
    - `second_question_while_pending_is_409`;
    - an ask returns 201 with the PSP text escaped (post `<b>x</b>`, assert it is escaped).
  - `ChatPanelTest`:
    - with `amira.enabled=true` the panel shows "Tanya AMIRA" and the retention note, and not "Perbualan AI belum tersedia";
    - disabled shows the old badge;
    - the thread renders the "Jawapan AI" eyebrow, the source link to `portal.library.download`, the "Panduan asas" eyebrow on fallback, the officer line when `mentions_outcome`, and the proposal button JSON;
    - `create?prefill[work_type]=tambahan&prefill[storeys]=3&prefill[work_type_bad]=x` preselects the valid values and ignores the bad one.
- [ ] **Step 2: Run `php artisan test tests/Feature/Amira`.** Expected: FAIL.
- [ ] **Step 3: Implement the routes, controller, partials, JS, CSS and prefill.**
- [ ] **Step 4: Run `php artisan test`.** Expected: the full suite passes.
- [ ] **Step 5: Commit** `feat(portal): chat with AMIRA in the Kaunter Maya panel`.

### Task 6: Operations, docs and live verification

**Files:**
- Create: `app/Console/Commands/PruneAmiraConversations.php`, `app/Console/Commands/AmiraConnectionCheck.php`, `docs/adr/ADR-369-amira-conversation-on-local-qwen.md`, `docs/runbooks/amira-chat.md`
- Modify: `routes/console.php` (`amira:prune` daily at 03:00), `AGENTS.md` (AMIRA chat section), the spec (add §8.6b "Step 4 decisions": the owner's model, limit and retention, plus the implementer's calls listed at the top of this plan), the iNDECS `.env` and AMIRA `.env` (local only, never committed: generate one 64-hex token shared as AMIRA's `indecs` client token and iNDECS `AMIRA_SERVICE_TOKEN`; never echo it)
- Test: `tests/Feature/Amira/PruneAmiraConversationsTest.php`

**Interfaces:**
- `amira:prune` deletes conversations whose `last_message_at` is older than `retention_months` and prints the count.
- `amira:check` calls capabilities and one canned question ("Apakah dokumen wajib untuk permohonan ini?") with an empty context, then prints the model, latency and `free_text_conversation`. It exits 1 on failure without printing the token.

- [ ] **Step 1: Write the failing test.** `PruneAmiraConversationsTest`: a conversation 12 months and 1 day old is deleted along with its messages; one 11 months old is kept.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement the commands, schedule, ADR-369, runbook and AGENTS section.** The runbook covers:
  - LM Studio `lms server start`, then `lms load qwen/qwen3.5-9b --context-length 8192`;
  - starting AMIRA with `START_AMIRA.cmd`;
  - the worker: `php artisan queue:work amira --queue=amira,amira-index --timeout=310`;
  - running `amira:index-library` once;
  - running `amira:check`;
  - swapping to the 27B or production model by configuration.
- [ ] **Step 4: Run `php artisan test`.** Expected: the full suite passes.
- [ ] **Step 5: Configure locally and verify live.**
  1. Set both `.env` files.
  2. Start AMIRA.
  3. Run `php artisan amira:index-library`, then `php artisan amira:check`. Expected: exit 0, with the model `qwen/qwen3.5-9b`.
  4. In the browser (port 8001, demo applicant, PB demo draft), check:
     - "Dokumen apa saya perlu?" lists the PB checklist;
     - "Tambahan 1 tingkat teres jadi 3 tingkat" shows a proposal card, and "Guna butiran ini" fills the form without saving it;
     - "Confirm lulus kan?" gets the officer line;
     - stopping LM Studio's server gives a "Panduan asas" reply.
- [ ] **Step 6: Commit** `feat(amira): retention, connection check, ADR-369 and runbook`.
