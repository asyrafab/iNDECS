# ADR-323: Natural planting close to the footprint

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested slightly larger plants closer to the footprint, with uneven,
natural positions rather than an evenly spaced border.

## Decision

Keep the seven original plant images. Increase their size to 15–17 drawing units
and vary their positions and rotations along the footprint's left and lower edges.
Keep each plant's maximum animated bounds outside the building's swept envelope.
Use fixed irregular positions so replay is stable and cannot introduce overlaps.

## Consequences

Planting reads as a loose cluster around the footprint. Motion, timing, assets
and the final mark are unchanged.
