# ADR-379: Landscape semicircle in the iNDECS mark

Date: 2026-11-12. Status: superseded by ADR-380. The initial implementation was rejected by the owner during visual review.

## Context

The owner requests a semicircle at the bottom right of the iNDECS logo to represent Landskap, alongside the three squares representing Perancang, Bangunan and Kejuruteraan.

## Decision

Preserve the existing three squares and add a filled teal, upward-facing semicircle in the lower-right space. In the mark's 120 × 120 coordinate system its diameter runs from (72, 120) to (120, 120), with its crown at (96, 96). The mark keeps its existing bounds and the curve stays separate from the squares.

Apply the same geometry to the shared `x-mark` component, normal/reversed SVG assets, lockup and final welcome-introduction symbol. Use the existing accent token in the UI and its existing asset colour in standalone SVGs. Preserve the introduction timing and reduced-motion fallback.

The three squares now represent planning, building and engineering; the semicircle represents landscape. This supersedes ADR-316's primary logo explanation. The architectural reveal remains a visual origin story, and the separate one/two/three-square relationship indicator retains its existing meaning.

## Consequences

All four departments are represented in the system identity without another accent colour or changes to access, department artwork or application behaviour. Update the public logo explanation and current design guidance with this meaning. Semicircle percentages/radii in CSS are logo geometry rather than component spacing or corner tokens.
