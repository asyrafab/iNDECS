# ADR-325: Random bloom order with a timed final plant

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested random bloom order including the two newest plants, with the
last plant finishing two to three seconds after the roof finishes moving aside.

## Decision

Shuffle all nine plants on every playback and add small timing variations. Keep
positions fixed. The two marked side plants may start only after the roof and
walls finish separating, so random scheduling cannot introduce overlaps.

Use a 700 ms bloom and finish the last randomly chosen plant 2.5 seconds after
separation completes. Fade landscape out after that, then crossfade the building
to the logo. Retain eight-second playback and existing skip / motion preferences.
Timings come from tokens; replay reschedules without creating more plant nodes.

## Consequences

The bloom reads as irregular growth while keeping the requested endpoint and
preserving clearance around the moving walls.
