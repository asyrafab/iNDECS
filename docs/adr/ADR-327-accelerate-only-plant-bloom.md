# ADR-327: Accelerate only the plant bloom

Date: 2026-11-03. Status: Accepted.

## Context

The owner clarified that the request for a much faster sequence referred only
to plant blooming. The full-speed change in ADR-326 was a misinterpretation.

## Decision

Restore eight-second playback and the previous roof-piece and dust timings.
Keep 260 ms plant blooms, with random starts concentrated near the end of
separation. Start the burst 1.5 bloom durations before separation finishes,
and complete its last plant 1.1 seconds after separation. The two marked side
plants still wait until the walls clear. Preserve all other animation phases.

## Consequences

Roof and building movement recover their approved pace. The random plant burst
lasts about 1.5 seconds while the rest of the introduction remains unchanged.
