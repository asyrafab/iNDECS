# ADR-339: Hill contours, prompt handoff and welcome emphasis

Date: 2026-11-03. Status: Accepted.

## Context

The owner clarified that the background must read as hill elevation contours,
asked to remove the idle wait after the opening, requested four swipe speed tiers
including super fast, a bold logo divider and a distinct iNDECS heading treatment.

## Decision

Draw visible groups of nested, irregular, closed elevation contours around hill
summits, with index contours. Keep their colours token-based and their contrast
subordinate to content. Replace the prior broad off-screen contour forms.

Begin the existing 900 ms tilted wipe at 6080 ms, when the logo wordmark completes
its fade. Preserve the eight-second story's animation/keyframe timing: remove only
its final 1920 ms static hold. Use a dedicated completion token for the handoff.

Quantise hover distance beyond the dead zone into four equal bands, at .35, .75,
1.2 and 2.4 track units per second. Keep opposite travel and stop-away behaviour.
Define the speeds as tokens. Show no extra controls or technical labels.

Use a 3 px navy token divider between the crest and iNDECS identity. Emphasise
iNDECS within the welcome heading with darker teal, weight 700 and modestly larger
type. Preserve spelling, the full heading semantics and the single-screen layout.

## Consequences

The background represents terrain planning, the visible animation retains its
pace, and the homepage arrives immediately after the final wordmark reveal.
This supersedes ADR-338's continuous hover-speed mapping.
