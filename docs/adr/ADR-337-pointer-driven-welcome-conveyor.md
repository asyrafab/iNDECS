# ADR-337: Pointer-driven image boxes and a single-screen welcome

Date: 2026-11-03. Status: Accepted.

## Context

The owner clarified that the image boxes themselves should move diagonally,
opposite the pointer, with speed increasing near either end. New images should
appear as boxes re-enter. They requested clean, maintained development imagery,
a tilted right-to-left entrance wipe, and a homepage without downward scrolling.

## Decision

Replace the in-frame slideshow with a continuous diagonal conveyor of exactly
three cropped boxes. Pointer position along the lower-left/upper-right axis sets
opposite travel direction and proportional speed, with a calm centre dead zone.
Recycle a box with a new decoded photo only while it is faded at the track edge.
Retain keyboard previous/next, touch gestures, pause and reduced-motion support.
Pause on keyboard focus, hidden pages and open information panels. Hover steers
the conveyor instead of pausing it. Background playback remains slow.

Remove the rural, overgrown and unmaintained photographs from the active pool.
Use clean architecture and maintained landscaping, preserving natural colours;
earthwork images may show an active construction site. Keep source provenance.

Reveal the stationary homepage by animating the entrance's tilted clip boundary
from right to left. Do not translate the opening artwork during this wipe.
Fit the header, copy, actions and gallery into one dynamic viewport, including
phone and landscape layouts. Information surfaces overlay the page with their
own bounded overflow, without extending the homepage.

## Consequences

This supersedes ADR-336's fixed panels and hover pause. Motion never changes
application state. The introductory story remains finite and unskippable, with
the existing failed-load, no-JavaScript and reduced-motion fallbacks.
