# ADR-336: Swipe entrance and three photographic carousel panels

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested removal of the initial logo flash, a swipe into the welcome,
three image boxes, cropped hover enlargement, closer Malaysian bungalow imagery,
more photographs with diagonal slideshow movement, inward-aligned welcome content
and a link from the MBSP crest to its official website.

## Decision

Set the entrance state before first paint. Hide the introduction artwork until
its textures and animation are ready. On completion, swipe the entrance left
to reveal the welcome; keep its controls inert until this transition finishes.
Retain the unskippable finite story and reduced-motion/no-JavaScript fallbacks.
A bounded bootstrap recovery must reveal the page if the landing script fails.

Use exactly three overlapping clipped panels, fed by a shared local photograph
pool. Slide images diagonally upper-right or lower-left; retain object-fit cover
when a panel rises and enlarges on hover, focus or touch selection. Use real
Malaysian house imagery, framed closely around its roof and building. Add previous,
next and pause controls, touch swiping and arrow-key navigation. Autoplay starts
only after the entrance, pauses during hover/focus, when the page is hidden, or
when the owner selects pause. Reduced motion disables autoplay and slide movement.

Inset the desktop branding and welcome copy toward the centre. Link the supplied
MBSP crest directly to https://www.mbsp.gov.my/ in the current tab.

## Consequences

Photographs stay cropped throughout interaction. Plan, earthwork, landscape and
building subjects remain available within three boxes. Source attribution stays
with the locally served assets. These choices supersede ADR-335's four panels,
full-image reveal and logo-to-header transition.
