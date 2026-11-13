# ADR-380: Owner-supplied iNDECS logo

Date: 2026-11-12. Status: accepted by owner.

## Decision

Adopt the owner's `iNDECS_New.svg`, with `iNDECS_New.png` as its visual reference, as the system logo from now on. Preserve its exact paths, square proportions, integrated outlined circular canopy and supplied colours. The squares represent Perancang, Bangunan and Kejuruteraan; the canopy represents Landskap.

This supersedes the unapproved detached semicircle implementation in ADR-379. Keep that record as history, marked superseded. Preserve source files under `public/img/brand/`, with provenance. The normal standalone mark is the unchanged supplied SVG. Derive a reversed outline variant for dark grounds, keeping the teal square unchanged. UI colours use dedicated brand tokens; general interaction tokens are unchanged.

Use the supplied geometry in the shared `x-mark`, lockup and final welcome animation. Shared inline shapes replace the approximate CSS squares; the animation retains its material sequence, timing and reduced-motion behaviour. The separate relationship indicator and department icons are unchanged.
