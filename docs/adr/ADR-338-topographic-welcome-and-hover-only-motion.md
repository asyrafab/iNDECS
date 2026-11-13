# ADR-338: Topographic welcome and hover-only motion

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested topographic background lines, both logos directly above the
welcome text, static images away from the pointer, faster hover-driven swiping,
removal of carousel buttons and more visible image shadows.

## Decision

Add a decorative inline SVG contour field behind the welcome, coloured through
shared tokens and excluded from interaction and the accessibility tree. Group the
MBSP crest and iNDECS identity above the heading inside the left copy column.
Preserve the crest's official-site link and the single-viewport layout.

Set idle conveyor velocity to zero. Steer only while the mouse is over an actual
image box, including hit-testing after moving frames so leaving a box immediately
stops motion. Increase maximum hover speed from .65 to 1.2 track units per second.
Retain cropped images, keyboard arrows, touch swipes and reduced-motion handling.
Remove all carousel controls and their pause state. Give the photo boxes a dedicated
material shadow token without changing shared card shadows.

## Consequences

The page remains still without interaction. This supersedes ADR-337's background
drift and visible carousel controls. Contours and photo shadows are narrowly
owner-approved additions to the welcome page.
