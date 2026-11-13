# ADR-369: AMIRA conversation on a local Qwen model

Date: 2026-11-06. Status: Accepted by the programme owner (Kaunter Maya step 4).

## Context

The spec (§8.1) has AMIRA's free-text conversation run on a self-hosted Qwen model behind the AMIRA backend
(ADR-353). It must be grounded in the department library and the PSP's own records, must never state an outcome,
and needs usage limits, an audit trail and a fallback before go-live. On this machine `qwen/qwen3.5-9b` answers in
4–9 s; `qwen/qwen3.8-27b` took 1–4 minutes.

## Decision

**Owner's decisions:**
- Development model `qwen/qwen3.5-9b` on LM Studio, with reasoning off.
- 30 PSP messages per account per calendar day (Asia/Kuala_Lumpur).
- Conversations kept 12 months after the last message.

**Where things live:**
- **AMIRA backend (stateless).**
  - `POST /api/v1/conversation` holds the persona, rules, prompt and the `LocalAiProvider`. It calls an
    OpenAI-compatible endpoint with JSON-schema output.
  - It drops cited source ids it was not given and proposal values outside the offered codes.
  - It strips markup and rejects empty, over-long or non-JSON output.
  - It flags outcome words.
  - It stores nothing and has no record access.
  - Moving to the production server changes only `AMIRA_LLM_*` settings.
- **iNDECS (owns everything else).**
  - It stores conversations in `core.amira_conversations` / `core.amira_messages`. Messages are append-only by
    trigger; whole conversations are deleted after retention by `amira:prune`.
  - It enforces the limits: one pending reply, 30 a day, 1000 characters, throttles.
  - It builds the context from the signed-in PSP's own records (owner decision, 2026-11-06: "AMIRA can reach all
    user information"):
    - the account: name, email, verification, district, join date and sign-in methods;
    - up to 20 drafts: title, address, notes, particulars, document progress, outstanding items;
    - the current draft's checklist, with uploaded file names;
    - up to 4 public library excerpts;
    - a pseudonymous subject.
  - It never sends credentials (password hash, tokens, Google subject), staff fields, document contents or any
    other user's data. It trims notes, then older drafts, to stay within AMIRA's 64 KiB request limit.
  - It calls AMIRA from the `amira` queue.
- **Library grounding.**
  - Each new library version's PDF text (pdftotext, no OCR) is indexed into `core.library_chunks`. Search is
    full-text (`simple` configuration) over active, public, current versions linked to the draft's type.
  - Citations link to the public download.
- **Answers.**
  - Replies are queued, and the panel polls the thread while AMIRA "thinks". Each reply is labelled "Jawapan AI".
  - If the reply mentions an outcome, a fixed line is added: "Keputusan dibuat oleh pegawai MBSP, bukan AMIRA."
  - Any failure (service or model down, bad output, worker crash) stores a scripted "Panduan asas" reply. It is
    built from iNDECS records, so no question stays unanswered.
- **No state changes by AI.**
  - A particulars proposal is a card. "Guna butiran ini" fills the draft form, or opens a prefilled new-draft
    form, and the PSP saves it.
  - The prefill is re-validated against `config/kaunter.php` and the portal types.
- **Agreement (owner, 2026-11-06).**
  - A one-time "Persetujuan penggunaan maklumat oleh AMIRA" opens on the PSP's first visit, after the entrance,
    and once per sign-in session until accepted.
  - It states what AMIRA uses and its limits: never passwords, IC numbers, sign-in tokens, document contents or
    other users. It also states the 12-month retention, and that AMIRA helps while officers decide.
  - `users.amira_consented_at` records the first acceptance, with an audit entry.
  - Without the agreement, AI chat is refused server-side (403) and the panel offers "Baca persetujuan". Local
    guidance still works.
  - The panel's retention note and footer were removed; that wording now lives in the agreement.
- **Panel.**
  - With `AMIRA_ENABLED=true`, the Kaunter Maya composer is the AI chat. Otherwise it keeps the ADR-368 saved local
    guidance.
  - Background requests from a signed-out PSP get 401, not a login page.
  - After the entrance, AMIRA settles to `--size-amira-bubble-compact` beside her name, so the conversation has the
    room.
  - Desktop: the panel slides in from the left once per sign-in session (`--duration-amira-slide`), pushing the
    file aside. Its width can be dragged, or set with the arrow keys, between 300 and 720 px. The width is saved per
    browser; double-click resets it.
  - Poking AMIRA wobbles her away from the finger and disperses bubbles. Six pokes within 3 s overload her:
    - she glows (`--color-amira-poke-glow`) and her rings startle;
    - she shrinks a little, then inflates past her size and bursts into a cloud of bubbles;
    - she is gone for a moment;
    - her body returns first, then her voice wave and mist fade back in (`--duration-amira-poke-*`).

    Reduced motion ignores pokes. Tests: `tests/Js/amira-poke.test.cjs`.
  - A hairline with a short grip on the panel's right edge marks the resize handle.
  - Moving a mouse over her body leaves a foam trail inside it: small bubbles and a faint wake that drift
    back and fade within about a second, like the white water behind a surfer. It is mouse-only, capped at 40
    foam bubbles and off with reduced motion.
  - Hovering also pulls her outline a little towards the cursor, up to 10%, relaxing about 0.25 s after the mouse
    stops or leaves.
  - Her canvas bleeds past the avatar (`--amira-canvas-bleed`, click-through), so the burst, glow and voice ring
    blend into the panel instead of being clipped to a box. Her body is still sized from the avatar.
  - Each new reply pops in and types out letter by letter (ADR-367 timing tokens); sources and cards appear when
    it finishes. Reduced motion shows it at once.

## Consequences

- To run, the AMIRA service, LM Studio's server and an `amira` queue worker are all needed
  ([runbook](../runbooks/amira-chat.md)).
- The 9B model can add general advice that isn't in its sources. Labelling, citations and the officer line
  mitigate this; a larger model is a configuration change.
- There is no officer view of conversations yet. The audit trail is the database.
- **Before go-live:** name the production model server, set HTTPS and an API key, and replace the demo reference
  document with real library content.

## Validation

- AMIRA backend: `php tests/run.php` and `py tests/http_smoke.py`.
- iNDECS: `tests/Feature/Amira/*`.
- Live: `php artisan amira:check`, plus the browser checks in the runbook.
