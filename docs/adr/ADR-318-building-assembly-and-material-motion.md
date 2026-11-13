# ADR-318: Building assembly and material motion

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested flying roof tiles that settle with a little dust, an opening
door during wall movement, and dust as the building layers separate.

## Decision

Extend the finite introduction to twelve seconds. Assemble the original roof
texture from staggered clipped pieces, preserving its colours and alignment.
Use a separately hinged photographic door detail over a masked doorway so the
closed door is not duplicated. Roof and walls still separate together (ADR-317).
Use brief, low-opacity neutral particle clusters for tile impacts and movement;
no blur, glow, gradients, continuous emitters or loops. Dust is limited to this
owner-requested architectural introduction.

Keep the final SVG mark unchanged. Effects are generated locally with SVG and
CSS using the existing textures; no additional image downloads are necessary.
Skip, Escape and either reduced-motion preference remove every effect and show
the final mark. Reset all effects on replay.

## Consequences

The sequence conveys material assembly and weight before simplifying to the mark.
All effect durations and easing come from tokens; decorative nodes are hidden
from assistive technology and cannot receive input.
