# ADR-319: Roof-first assembly and landscape bloom

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested that walls and footprint remain invisible during roof
assembly, that the door open outward, and that exterior landscaping bloom when
the roof moves aside. This refines the material introduction in ADR-318.

## Decision

Keep both lower photographic layers fully transparent through the roof assembly
and initial hold. Reveal them only as the roof and walls begin separating.
Reverse the door hinge rotation and its swing arc towards the exterior.

Place naturally coloured, top-down tropical planting outside the footprint.
Scale and fade the plants into view with a short stagger during separation,
then fade them away before the logo settles. Expand the illustration viewport
to accommodate planting without changing the final logo's geometry. No new
interface accent is introduced; foliage retains its natural photographic colour.

Keep the twelve-second sequence, shared movement timeline, replay, skip,
Escape and reduced-motion fallback. Decorative planting is invisible in the
static final mark and when either reduced-motion preference is active.

## Consequences

One additional transparent WebP asset is served locally. The assembly begins
with roof pieces alone; the revealed landscape gives the building a site context.
