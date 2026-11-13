# ADR-374: Transparent Esri chooser identity

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

At the owner's request, supersede ADR-373's white identity ground and border.
Show the full Esri logo directly on the chooser tile's own surface, retaining
its size, aspect ratio, transparent source and inset. The black logo is shown
normally on light surfaces and white on dark surfaces through a filter token.
The original SVG bytes remain unchanged. Theme-specific values live only in
tokens.css; no component selector branches on theme. Routing and access do not
change.
