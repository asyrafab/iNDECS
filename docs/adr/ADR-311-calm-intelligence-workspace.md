# ADR-311: Calm Intelligence across the iNDECS workspace

Date: 2026-10-28. Status: Accepted.

## Context

The programme owner approved the interactive navy-sidebar concept, retaining
Google Sans, the three-square iNDECS mark, a shared teal accent, purposeful motion
and AMIRA as a supporting assistant. The deployed styles still describe the
superseded red Modernist theme. Bangunan registration and document verification
are already being developed in this checkout and must keep their existing guards.

## Decision

- Apply one token-based visual system to all existing staff, legacy and public
  screens. Use a persistent navy sidebar, light working surfaces, thin borders,
  Google Sans 400/600 and the shared teal accent. Keep light, dark and system modes;
  retire selectable accent colours, including stale browser preferences.
- Use department names, Lucide icons and permission-filtered workspace links for
  module identity. Do not add unavailable departments, dead links or a new colour
  scheme per department. Preserve routes, form contracts and workflow history.
- Retain the mark geometry and recolour its leading square teal. Use reversed
  artwork on navy surfaces. Recur at workspace, footer, public identity and
  assistant touchpoints. The MBSP crest leads on identity pages only.
- Motion is finite: mark assembly on entry, short view/panel transitions and
  interaction feedback. Respect reduced motion. Provide keyboard focus, mobile
  navigation, accessible assistant controls and a motion preference.
- AMIRA is a shared context-aware guidance panel. Until an authenticated AIRS
  assistant API is available, its answers are explicitly local page guidance,
  grounded in the currently authorised page and links. No fabricated findings,
  confidence scores, live-service claims or external transmission. AMIRA cannot
  submit forms, change workflow state or make officer decisions. Connecting live
  AI requires a separate implementation under the existing integration contracts.
- Ship the Lucide subset locally, with its upstream licence and version recorded,
  so icons work without a runtime CDN or frontend build.

## Consequences

Existing Blade components remain the class layer. Theme differences live only in
tokens. The new shared shell covers Bangunan, Perancang and read-only legacy
screens without migrating business logic. Real AIRS chat and new departmental
workflows remain separate capabilities, with their availability stated in the UI.
Validate the PostgreSQL regression suite, compiled views, light/dark modes,
keyboard interaction, narrow layouts and authenticated Bangunan screens.
