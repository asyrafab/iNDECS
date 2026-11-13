# ADR-328: Start blooming as the roof moves aside

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested that plant blooming start immediately as the roof moves to
the side, rather than near the end of separation as in ADR-327.

## Decision

Schedule the first eligible plant exactly at the start of roof movement (25% of
playback). Randomise which eligible plant starts and the remaining order. Retain
260 ms blooms, the existing final-plant endpoint and the original building pace.
The two wall-adjacent plants retain their clearance constraint and cannot start
the sequence. This supersedes ADR-327's compressed scheduling window.

## Consequences

Quick individual blooms accompany roof movement immediately, with later blooms
continuing through the existing endpoint.
