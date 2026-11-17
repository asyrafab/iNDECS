# ADR-387: Clickable public draft cards

Date: 2026-11-16

Status: Accepted (owner request)

## Decision

Each Fail saya draft card uses one native title link stretched across the card.
The card opens the existing owner-scoped draft editor through ordinary navigation,
including keyboard, new-tab and no-JavaScript support. The removal form remains
a separate interactive control above the stretched link, preserving confirmation
and draft-only eligibility. Do not nest buttons inside links or use row click JS.

Group draft type and document progress below the title and application name to
give the main text room. Keep a light Sambung cue beside the removal action and
provide whole-card hover/focus feedback through shared tokens. On phones the
actions wrap below the content. Replace obsolete availability copy with a short
explanation of the draft card action.
