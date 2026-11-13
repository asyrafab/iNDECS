# ADR-381: Circular garden in the opening animation

Date: 2026-11-12. Status: accepted by owner.

## Decision

Add a material landscape layer to the architectural opening: grass inside the approved logo's circular landscape element, with a stone walking path following its outlined arc. Keep the approved logo paths unchanged (ADR-380).

Place the garden behind the building layers so its overlap matches the final circular element. Reveal the garden as the building layers separate, then fade its materials into the shared logo during the existing symbol transition. Reuse the existing natural lawn texture and add a natural stone paving texture. Preserve existing planting, roof/door motion, total entrance timing, welcome wipe and no-JavaScript/reduced-motion fallback. Decode the new material with the other textures; failed loading must still reveal the welcome.

The path is animation artwork only. Its width is intentionally legible as a walking surface before the thinner approved outline appears. Clip grass and stone to the same circular garden footprint; do not add a permanent grass fill to the final logo.
