# ADR-361: Kaunter Maya portal shell and AMIRA bubble avatar

Date: 2026-11-05. Status: Accepted by the programme owner.

## Context

The programme owner approved the Kaunter Maya design
([spec](../superpowers/specs/2026-11-05-public-portal-kaunter-maya-design.md)).
The public portal becomes MBSP's online counter for Professional Submitting
Persons (PSPs). AMIRA is the attending counter officer from first conversation to
validation pass, and the portal should feel casual and clear rather than like a
form-heavy office portal. This ADR covers step 1 of the delivery order: the shell
and the avatar over the existing preparation drafts.

## Decision

- **Layout.** Supersede ADR-359's two-choice entrance. Portal home and the draft
  editor use the `x-portal-counter` split: AMIRA on the left (persistent navy
  panel, `partials/amira-counter`) and the applicant's file on the right.
  - The home is **Fail saya**, grouped into Perlu tindakan anda, Draf belum
    dihantar and Selesai / Ditutup. Only non-empty groups are shown.
  - "Permohonan baharu" opens the existing type chooser (ADR-360 downloads
    unchanged).
  - The Semak Permohonan dialog is removed.
  - Pages without the counter (account, draft list) keep the AMIRA drawer.
- **Phone.** Below 901px, Fail saya fills the width. AMIRA waits in a fixed
  "Tanya AMIRA" bar and opens as a full-height sheet (dialog semantics, focus
  trap, Escape and focus return).
- **Avatar.** AMIRA is an uneven bubble with three horizontal wavy rings: the
  bubble is her body and the rings are her voice. `public/js/amira-bubble.js`
  animates every `[data-amira-bubble]` from `data-mood`
  (calm/happy/think/concern/alert) and `data-speaking`, and exposes
  `window.AmiraBubble.setMood/setSpeaking`. Colours come only from tokens.
  Reduced motion draws one still frame. Without JavaScript, a static
  token-coloured circle shows.
- **Exception.** The radial gradient and soft glow inside the avatar canvas are
  an owner-approved exception to the "no gradients, glows" rule, limited to the
  avatar canvas. Nothing else may use them.
- **Identity.** AMIRA is labelled "Pegawai Kaunter Maya" inside the page. The
  header brand remains iNDECS · Portal Awam · MBSP, and the footer keeps "AMIRA
  membantu. Pegawai memutuskan."

## Boundaries

AMIRA remains labelled local guidance ("Perbualan AI belum tersedia"). There is
no model call, no fabricated status, no submission and no status change. Drafts
are still not applications: every row carries "Draf" and "Belum dihantar kepada
MBSP". No new data, migration or permission is introduced. `PortalBoard` scopes
every query to the owner. Conversation on the internal Qwen model, uploads,
payment, outcomes and groups follow in later steps of the spec.

## Validation

`tests/Feature/Portal/*` (PortalBoard, counter layout, phone bar, bubble markup
and tokens-only script) and the updated `PublicPortalTest`. The avatar, theme
switch, reduced motion and the phone sheet were checked in a browser.
