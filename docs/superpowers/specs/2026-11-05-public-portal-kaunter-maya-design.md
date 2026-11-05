# Public portal "Kaunter Maya" with AMIRA — design

Date: 2026-11-05. Status: Approved by the programme owner (2026-11-05).
Source: brainstorming session with the programme owner. Mockups:
`.superpowers/brainstorm/47-1793782350/content/` (`split-journey.html`,
`psp-home.html`, `amira-emotion.html`).

## 1. Intent

Today a Professional Submitting Person (PSP) pre-consults Jabatan Bangunan at
the counter, gathers every required document and plan, and only then submits
to OSC Online. The public portal moves that counter online as **MBSP Kaunter
Maya**, so what reaches OSC Online is already clean and OSC processing time
drops.

The portal must not look or feel like a typical government or office portal.
It should feel casual, clear and easy, built around one journey:
**apply → upload documents → system checks/revision → validated**.

**AMIRA** is the counter officer. She is the visible, constant guide from the
first conversation to the validation pass. She knows the guidelines,
standards and checklists. PSPs talk to her the way they would talk to any AI
assistant, and a new application starts with a conversation with her, not a
form.

## 2. Users

- **Primary: PSPs** (architects, draughtsmen, engineers) applying for
  clients. They usually run several applications at once and return often.
  They know the trade vocabulary but not every MBSP-specific rule.
- **Devices:** documents live on PCs, so desktop is the main working surface.
  The phone is used to check status and ask quick questions.

## 3. The journey

| # | Stage | Cost | What happens |
|---|---|---|---|
| 1 | Guided intake | Free | A conversation with AMIRA establishes what is being built and where. AMIRA then lists the required documents and each one's requirements. |
| 2 | Pre-check | Free, repeatable | Uploads are checked against the checklist, plus a simple OCR check per document (readable, right type of document, key fields present). The PSP fixes and re-uploads as often as needed. |
| 3 | Payment | Paid | Unlocked only when every checklist item passes the pre-check. |
| 4 | AIRS review | — | AIRS reviews each document in depth and produces a validation report. |
| 5 | Officer validation | — | An officer validates the AIRS report. Officers decide; AIRS is advisory. |
| 6 | Outcome | see §3.1 | Pass, Pindaan Kecil, Pindaan or Tolak. |

A pass gives the PSP a **validation pass (Pas Pengesahan)** to use when
submitting on OSC Online. This portal does not submit to OSC.

### 3.1 Outcomes

| Outcome | Meaning | Next step | Cost |
|---|---|---|---|
| **Lulus / Pas sah** | Validated | Download the validation pass | — |
| **Pindaan Kecil** | Returned for a minor adjustment or an officer request | Fix the named items and resubmit to the officer | Free |
| **Pindaan** | Did not meet the validation threshold | Fix, pay a reduced re-check fee, AIRS runs again, then back to the officer | Reduced fee |
| **Tolak** | Rejected; no re-check is possible | Start a completely new application | Full fee |

A Tolak closes the file. It stays visible to the PSP as a **read-only
record**: they can read the officer's reason, the AIRS report and officer
notes, and download what they submitted. Nothing carries over automatically
into a new application.

## 4. Layout

### 4.1 Desktop: AMIRA beside the file (chosen layout "B")

A split screen, used across the whole journey:

- **Left (about one third): AMIRA.** A navy panel with her avatar, the name
  "AMIRA · Pegawai Kaunter Maya", the conversation, quick-reply chips, and an
  input where files can also be dropped. She is always present.
- **Right: the file.** It changes with the stage:
  - **Intake:** the file "builds" as the conversation goes. Gathered
    particulars (type, address, storeys…) fill in as AMIRA learns them. The
    document list appears once she knows enough.
  - **Pre-check:** the document checklist. Each document shows a pass or
    needs-attention state and a one-line reason. "Bayar semakan AIRS" stays
    disabled until every item passes.
  - **Waiting:** a vertical timeline (documents complete, payment, AIRS
    review, officer validation, pass), with the current step highlighted.
  - **Outcome:** an outcome badge that states the cost in plain words (for
    example "Pindaan Kecil · percuma · hantar semula sebelum 19 Nov"). The
    documents needing work come first, then those already passed.

### 4.2 Home: "Fail saya"

On the home page, the right side lists all of the PSP's files, grouped as
**Perlu tindakan anda** (needs your action), **Sedang berjalan** (in progress)
and **Selesai / Ditutup** (completed or closed, including Tolak and used
passes). Each row shows the project, the stage and a status pill. There is a
primary "+ Permohonan baharu" (new application) button.

On the left, AMIRA opens with today's to-dos, for example "2 fail perlukan
tindakan anda hari ini" with the reasons.

A PSP can start a new application in one sentence ("Tambahan 1 tingkat,
teres, Lot 4412…"). AMIRA extracts what she can and asks only for what's
missing. A less experienced user still gets a step-by-step conversation.

### 4.3 Phone: check and ask

- A status list of files, including downloading the validation pass.
- A "Tanya AMIRA" (ask AMIRA) entry for quick questions.
- No uploading or document fixing on the phone. Those tasks point the user to
  a computer.

### 4.4 Tone

AMIRA is casual and friendly, and speaks to PSPs as fellow professionals.
She doesn't over-explain trade basics, but she always spells out
MBSP-specific requirements and officer notes in plain Bahasa Melayu.
Whenever money is involved, she says plainly what it costs.

## 5. AMIRA's avatar

The avatar is **an uneven bubble with horizontal wavy rings**. It is not an
orb and not an illustrated character.

- **The bubble is her body.** It is never a perfect sphere: it is always
  slightly lopsided and slowly shifting, and translucent with a glossy
  highlight. Her emotion changes its shape, motion and colour.
- **The rings are her voice.** Three nested rings sit horizontally around
  the bubble, Saturn-style, in one flat band. The front half of each ring
  passes in front of the bubble and the back half goes behind it. When she
  is quiet, the rings are nearly flat with a gentle ripple. When she speaks,
  in any mood, they become sound waves that are strongest at the sides and
  pulse with her speech.

Initial moods (the programme owner will refine these later):

| Mood | Used when | Motion and colour |
|---|---|---|
| Tenang (calm) | Idle, waiting for the PSP | Slow breathing, gently lopsided; teal |
| Gembira (happy) | Pre-check passed, validation pass issued | Bouncy, stretches upward; brighter teal |
| Berfikir (thinking) | Checking a document, AIRS running | Leans and churns faster; purple (AI colour) |
| Prihatin (concerned) | Pindaan, Tolak | Sinks and flattens slightly, slow; muted slate |
| Perlu tindakan (needs action) | Something is due from the PSP | A small nudge every few seconds; amber |

Moods blend smoothly from one to the next. The avatar must respect reduced
motion (`prefers-reduced-motion` and `data-motion="reduced"`) by holding a
still pose. Its colours come from tokens (accent, status-ai,
status-attention, neutral), with dark and light variants. The reference
implementation is canvas-based (`amira-emotion.html`). Any rendering
technique that preserves this behaviour is acceptable.

## 6. Goals, non-goals, anti-goals

**Goals**
- A PSP can get from "I want to build X" to a full, correct document list
  without visiting the counter.
- Pre-check failures are specific and fixable without phoning MBSP.
- At every moment the PSP knows where each file stands and what they owe, in
  time and money.

**Non-goals**
- Submitting to OSC Online, or replacing it.
- Officer-side screens beyond the outcome actions in §8.3 and the library
  in §8.6.
- An illustrated or human avatar (possibly later).
- Uploading documents from a phone.

**Anti-goals (a failure even if it works)**
- It looks like a form-heavy government portal: walls of fields, tables,
  department menus.
- A rejection or Pindaan with no plain-language explanation or next step.
- AMIRA appearing to make the decision. Officers decide, and AMIRA must say
  so.
- Surprise charges. Every fee is stated before the PSP commits.

## 7. Constraints from the existing system

- **Visual system:** use only the tokens in `public/css/tokens.css` (the
  current navy/teal "Calm Intelligence" set). `CLAUDE.md`'s summary
  describes an older red Modernist system and is stale; tokens and
  `docs/DESIGN-DIRECTION.md` govern. Never hard-code colours, fonts or
  spacing. Bahasa Melayu throughout, Lucide icons, dark mode and reduced
  motion supported.
- **ADR-352:** iNDECS owns identity, drafts, submission, payments,
  corrections, decisions and letters; AIRS owns review artefacts; scores are
  advisory; officers decide. The free pre-check uses checklist/file
  validation and local OCR only, with **no paid generative AI**.
- **ADR-353:** AMIRA's backend lives in `C:\Users\Asyra\Documents\AMIRA`.
  Free-text conversation is not implemented there yet, and it needs a
  delegation, audit and usage-budget design before activation (see §8.1).
- **ADR-358/359:** the existing `/portal` (drafts, two-choice front page,
  gear menu) is the starting point. This design **supersedes ADR-359's
  two-choice front page** with "Fail saya" plus AMIRA. Drafts are still not
  official applications.

## 8. Decisions (programme owner, 2026-11-05)

### 8.1 AMIRA conversation runs on an internal LLM (Qwen)
AMIRA's free-text conversation uses a **self-hosted Qwen model** on MBSP
infrastructure, not a paid external API. This keeps the free stages free.
It refines ADR-352 ("no paid generative AI" still holds: an internal model is
not a paid per-use service) and activates the conversation that ADR-353
deferred.
- The model sits behind the existing provider-neutral `AiProvider` contract,
  as the planned `LocalAiProvider`, served through an OpenAI-compatible
  endpoint (LM Studio now; Ollama or vLLM are also acceptable later). The endpoint URL and model name are
  configuration.
- Conversation lives in the AMIRA backend (`C:\Users\Asyra\Documents\AMIRA`),
  called by iNDECS server-side with the PSP's scoped identity. It is never
  called from the browser.
- **Hosting (interim):** the production server will be named later. For
  now the model runs on this development machine's GPU (NVIDIA RTX PRO 2000
  Blackwell laptop, 8 GB VRAM) under **LM Studio**, with the model
  `qwen/qwen3.8-27b` already downloaded. LM Studio's Model Server exposes an
  OpenAI-compatible API (default `http://localhost:1234/v1`). A 27B model
  does not fit in 8 GB of VRAM, so LM Studio will offload part of it to the
  CPU and replies will be slow. That is acceptable for development. A
  smaller Qwen can be swapped in through configuration if the speed hurts
  testing. Moving to the real server must be a configuration change only
  (base URL, model name, key).
- Before go-live: a per-PSP usage limit (messages per day), conversation
  audit logs, and a fallback to scripted guidance when the model is down.
- AMIRA grounds her answers in the department library (§8.6) and the
  application's own records. She does not invent requirements: when unsure,
  she says so and points to the source document or the helpdesk. She never
  states an outcome; officers decide.

### 8.2 Scope: the essential journey works end to end
Delivery includes the essential working functions, not only the new look:
guided intake, upload, free pre-check, payment, AIRS review, officer
outcome, validation pass and the Fail saya home. Anything not yet live is
labelled honestly ("belum tersedia").

### 8.3 Officer outcome actions (staff workspace)
Add four officer actions to the staff workspace: **Lulus**, **Pindaan Kecil**,
**Pindaan** and **Tolak**. Each one records:
- notes to the applicant (required for anything other than Lulus);
- the specific documents or items to fix (Pindaan Kecil and Pindaan);
- a resubmission deadline (Pindaan Kecil and Pindaan).

This reverses §6's non-goal for these actions only. Other officer screens
stay as they are.

### 8.4 Pas Pengesahan
- Issued on Lulus as a PDF with a reference number and a QR code.
- The QR opens a public verification page showing whether the pass is valid,
  its reference, the project, the PSP and the expiry date. It shows no
  documents.
- **Valid for 6 months** from issue. After that, the verification page shows
  it as expired.
- There is no OSC Online integration in this scope. "Used" is not tracked;
  passes move to Selesai once expired.

### 8.5 Groups: sharing files within a team
PSPs can create a **group** (for example their firm or a project team),
invite other registered PSPs, and share applications with the group.
- Each application has one owner (the creator) and may be shared with one
  group.
- Group members can view, upload and fix documents and chat with AMIRA on
  shared files. Only the owner (my call) can pay, and only the owner can
  receive and act on outcome notices.
- Group roles: group admin (invites and removes members) and member.
- Every action on a shared file records who did it.
- Fail saya shows both "Fail saya" (my files) and the group's files, with a
  filter.

This changes ADR-358's owner-only scoping into owner-plus-group scoping, so
it needs a new ADR.

### 8.6 Department reference library
Each department has a **library** where its staff upload reference
documents: guidelines, standards, by-laws, checklists, sample plans.
- AIRS looks up these documents as reference during review.
- AMIRA uses the same library to answer PSP questions and to explain
  requirements, citing the document she used.
- Library documents are versioned. **Only a Penolong Pengarah** of the
  department can upload new documents, or replace and retire existing ones.
  A PSP can see whatever the department marks as public guidance (my call).
- The required-document checklist for each application type and its
  variants stays a **structured rule list** (my call). The library explains
  the rules; it does not replace them, because the payment gate must be
  deterministic. **Only admins** can set up and change these rules.

### 8.7 Deadlines
For Pindaan Kecil and Pindaan, AMIRA reminds the PSP 3 days before the
deadline. If the deadline passes, the file closes as Tolak, and the PSP
must start a new application.

### 8.8 Fees
Pindaan Kecil is free. Pindaan has a reduced re-check fee. Tolak means a new
application at the full fee. The amounts are still to be confirmed (§10).

**Gateway: MBSPPAY** (Plamera Technology, API v2.5), which provides FPX B2C,
FPX B2B1 and card (MIGS).
- **Pay:** the server POSTs to `https://mbsppay.mbsp.gov.my/ws/gateway.php`
  with a unique `batch_id` (at most 20 characters), `total_amount`,
  `datajson` (base64 JSON of `no_akaun`, `keterangan`, `amaun`, `nama`),
  `hash` = SHA1(batch_id + total_amount + secretkey), `email`,
  `callback_url` and `return_url`.
- **Result:** MBSPPAY calls `callback_url`/`return_url` by GET with a
  base64 `datareturn` that contains status, trxId, receiptNo and so on.
  This callback is **not signed**, so it is never trusted on its own. The
  server always confirms with the Transaction Status API
  (`GET https://mbsppay.mbsp.gov.my/api/transactionStatus`, `Secretkey`
  header, base64 `{batch_id}`) before marking anything paid. The same query
  runs as a scheduled job for payments whose callback never arrives.
- The secret key stays server-side in configuration. It is never sent to
  the browser or logged. Callback handling is idempotent per `batch_id`.
  The MBSP receipt number is stored and shown to the PSP.
- **Registration:** MBSP IT (it@mbsp.gov.my) issues the API approval and
  the secret key.

### 8.9 Builder calls made during review
- **Pre-check labels.** The free pre-check uses the contract's neutral
  labels: Dilampirkan, Belum dilampirkan, Tidak dapat dibaca and Perlu
  pengesahan pemohon. It never uses lulus or pass language before
  AIRS/officer review. The payment gate opens when every required item is
  Dilampirkan and readable.
- **Draft vs application.** A draft becomes an official application when
  payment clears.
- **Department scope.** Jabatan Bangunan is the first department. The
  library and groups are built for all departments.

## 9. Delivery order

This is several connected projects. Suggested order, each planned and
shipped on its own:
1. **Portal shell and AMIRA avatar.** Fail saya home, split layout, bubble
   avatar, phone status view, over the existing drafts.
2. **Department library and checklist rules.** Staff side. Upload,
   versioning, and the structured required-document rules per type.
3. **Upload and free pre-check.** Checklist matching and local OCR, with
   contract labels.
4. **AMIRA conversation on Qwen.** Model server setup, `LocalAiProvider`,
   AMIRA backend chat grounded in the library, usage limits, audit, fallback.
5. **Payment, then AIRS review.** Payment gateway, then the
   accepted-submission and AIRS review request.
6. **Officer outcomes, deadlines and Pas Pengesahan.** Staff actions,
   reminders, auto-close, the QR pass and the verification page.
7. **Groups and sharing.**

## 10. Still open

None of these block steps 1–4. Each must be settled before its own step:
- **Production Qwen server** (step 4 go-live): to be named by the programme
  owner.
- **Fee amounts and the MBSPPAY `no_akaun`** (step 5): the revenue account
  number or code to charge pre-check validation fees against, the full and
  reduced fee amounts, and the MBSPPAY secret key from MBSP IT.
- **Who writes the first checklist rules** (step 2): admins set them up, but
  the content comes from Jabatan Bangunan.

## 11. Left to the builder

- Component structure, routes and how the split panel is implemented, within
  the existing Blade, CSS and JS stack.
- Exact wording of microcopy, following §4.4.
- Avatar rendering technique, within §5.
- Phone breakpoints and how the AMIRA entry appears on a phone.
- Delivery order. The design can ship in increments (for example avatar and
  layout first, live conversation later), but each increment must state its
  service limits honestly, as ADR-358/359 already do.
