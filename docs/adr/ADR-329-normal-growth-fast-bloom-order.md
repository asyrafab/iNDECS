# ADR-329: Normal plant growth with faster bloom ordering

Date: 2026-11-03. Status: Accepted.

## Context

The owner clarified that individual plants should bloom at normal speed, while
the sequence of plant starts should be faster. Earlier changes accelerated the
individual growth instead of compressing the gaps between starts.

## Decision

Restore each plant's 700 ms growth. Randomise and distribute all nine starts
over one second, beginning when the roof starts moving aside. Plants therefore
overlap in growth rather than growing unnaturally quickly. Keep positions fixed.

The two side plants start no earlier than 40% through separation. With the
existing easing, the wall has cleared both marked locations by that point.
Their starts remain within the same one-second window. All nine are fully grown
1.7 seconds after the first bloom. This supersedes previous final-bloom tails.

Keep roof, building, dust, logo and eight-second playback timings unchanged.

## Consequences

The sequence feels quick through overlapping normal-speed growth. Random order,
instant first bloom, clearance and reduced-motion behaviour are preserved.
