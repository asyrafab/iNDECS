# ADR-317: Coordinated roof and wall reveal

Date: 2026-11-03. Status: Accepted.

## Context

The owner clarified that the walls should move aside at the same time as the
roof. This supersedes only the sequential separation described in ADR-316.

## Decision

Use one shared separation timeline for both layers: the roof travels towards the
upper right while the walls travel halfway along the same diagonal, revealing
the stationary footprint. Both start and finish together. Retain the hold,
crossfade into the existing mark, replay, skip and reduced-motion behaviour.

## Consequences

The architectural reveal reads as one coordinated separation of three layers.
