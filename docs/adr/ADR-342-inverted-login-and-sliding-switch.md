# ADR-342: Inverted login surfaces and a sliding account switch

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested distinctive inverted public/staff login backgrounds and an
on/off-style account selector with a moving highlight, bold selected text and
labels centred inside their bounds.

## Decision

Keep the two account choices as accessible tabs, presented as a two-position
segmented switch. A single background thumb slides between equal-width halves;
labels are centred horizontally and vertically, and selection uses weight 700.
This is an owner-approved exception to flush-left button labels for this selector
only. Retain keyboard arrows/Home/End, ARIA selection, focus and reduced motion.

Public login uses the theme's standard surface. Staff login uses an inverted
palette: navy with pale text in light mode, pale with navy text in dark mode.
Define staff palette inversions entirely in tokens, including fields, secondary
text, links, selector and submit button. Component selectors branch on account
type, never on theme. Transition colours alongside the sliding thumb.

## Consequences

The account type is visible across the whole popup. Existing forms, validation,
credential fields, endpoints, tab semantics, frosted backdrop and modal controls
remain intact. Reduced motion changes the selected position immediately.
