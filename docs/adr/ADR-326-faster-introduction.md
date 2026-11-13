# ADR-326: Much faster introduction

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested a much faster sequence after reviewing the random bloom.

## Decision

Reduce the introduction from eight to four seconds, roof-piece flight from
850 to 400 ms, roof stagger from 38 to 18 ms, plant bloom from 700 to 260 ms,
and the final-plant tail from 2.5 seconds to 1.1 seconds after separation.
Shorten dust to match. Keep the random bloom order, side-plant clearance,
geometry, replay and reduced-motion behaviour. This supersedes ADR-325's tail.

## Consequences

Roof separation ends at two seconds, the final plant completes at 3.1 seconds,
and the logo is fully visible at approximately 3.76 seconds.
