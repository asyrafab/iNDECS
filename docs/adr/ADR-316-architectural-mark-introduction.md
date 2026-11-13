# ADR-316: Architectural meaning and introduction of the iNDECS mark

Date: 2026-11-03. Status: Accepted.

## Context

The owner describes the three offset rectangles as a building viewed from above:
roof, walls and footprint. A realistic Malaysian clay-tile roof study and its
three-layer composition were approved in conversation. ADR-313 provides a finite
introduction but does not explain the mark's architectural origin.

## Decision

Show the complete roof, slide it towards the upper right to reveal the walls,
then separate the wall plan from the footprint at the lower left. Hold the
three layers, then crossfade their photographic details into the exact existing
mark geometry: two navy outlines and one solid teal square. Do not tint photos.

Use an inline landing-page introduction with replay and skip controls, rather
than the former full-screen overlay. Play once after the images have decoded.
Reduced motion, missing images or unavailable animation support show the final
mark immediately. Escape and skip settle on that mark. All timings use tokens.

The roof, walls and footprint are the primary architectural meaning; the
standalone / linked pair / development case indicator remains a secondary use.

## Consequences

Three independent generated transparent WebP assets (encoded from generated PNG originals) are served locally. Motion
does not block navigation or change application state. The SVG mark retains its
original geometry and takes its colours from shared tokens in both themes.
