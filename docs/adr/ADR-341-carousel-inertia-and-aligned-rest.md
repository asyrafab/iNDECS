# ADR-341: Regional steering, inertial drag and aligned carousel rest

Date: 2026-11-03. Status: Accepted.

## Context

The owner clarified that the conveyor should complete its travel to an aligned
three-box rest, with clear images, rather than stop between positions. Pointer
steering must work across gaps. They requested drag-and-release spinning, a more
obvious selected login tab, and the submit button above recovery/registration.

## Decision

Steer by pointer position anywhere in the gallery region, preserving opposite
direction and the four speed tiers. Remove image hit-testing. When steering stops,
continue toward the next half-track alignment in the current direction with a
bounded eased settle. At its endpoint show three fully opaque boxes, upper-right
on top. Do not reorder DOM boxes or teleport their positions to park them.

Support primary mouse, pen and touch dragging with pointer capture. Project drag
movement onto the conveyor diagonal, estimate release velocity, cap it, and decay
it before settling to an aligned position. A new drag or pointer input interrupts
the previous motion. Prevent native image dragging and text selection. Cancel on
lost capture and pause safely for dialogs, hidden pages and keyboard focus.
Reduced motion uses immediate aligned photos and discrete gesture navigation.

Style the selected login tab with a deeper teal fill and on-accent text; keep ARIA
tab selection and keyboard navigation. In both credential forms place Log Masuk
directly after the password, followed by recovery and registration links. Keep
the existing endpoints, field names, CSRF and account boundaries intact.

## Consequences

This supersedes ADR-340's instant parking and ADR-338's image-only hover gate.
The carousel has a finite inertial tail, then becomes fully clear and still.
