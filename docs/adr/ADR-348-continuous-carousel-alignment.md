# ADR-348: Continuous carousel alignment

Date: 2026-11-03. Status: Accepted.

## Decision

Blend coasting into alignment while speed is still .65 track units/second,
rather than waiting until .04 and restarting a separate ease-out. Carry the
actual velocity into a cubic Hermite curve with zero velocity at the final
aligned position. Choose a forward stop with enough braking distance and scale
duration to the incoming speed, capped by a 1440ms token. A stationary release
uses a gentle ease-in/out. Keep velocity and position continuous through the join.

Integrate exponential coasting exactly, independent of frame rate. Preserve the
60% hover takeover rule, additive drags, aligned opaque rest, reduced-motion and
modal/focus/visibility parking. This supersedes ADR-341's independent cubic
ease-out for alignment, which restarted rapidly after inertia nearly stopped.
