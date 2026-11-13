# ADR-331: Logo transition another half-second earlier

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested moving the final logo transition another 0.5 seconds earlier.

## Decision

Move its start from 4.4 to 3.9 seconds and completion from 4.96 to 4.46 seconds,
retaining the 560 ms crossfade. Fade landscape between 3.75 and 4.14 seconds,
after the last plant finishes at 3.7 seconds. Other timings remain unchanged.

## Consequences

The logo crossfade overlaps the final 0.1 seconds of layer separation and settles
at 4.46 seconds. This supersedes ADR-330's final-transition timings.
