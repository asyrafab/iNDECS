# ADR-334: Shadows beneath roof, walls and plants

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested shadows for the roof, walls and planting in the introduction.

## Decision

Add restrained material shadows using CSS drop-shadow filters on the photographic
roof group, photographic wall group and individual plants. Use alpha silhouettes
so transparent image corners do not cast rectangular shadows. Roof shadows are
slightly deeper than wall shadows; plants receive a small ground-contact shadow.
All filter values and shadow colour come from shared tokens.

Keep shadows attached to the photographic content so they disappear with its fade.
The final SVG symbol and wordmark remain flat. No other timing or geometry changes.

## Consequences

The architectural layers gain depth while preserving the approved final mark and
the existing static and reduced-motion fallback.
