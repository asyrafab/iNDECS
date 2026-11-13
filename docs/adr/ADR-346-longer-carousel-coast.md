# ADR-346: Longer carousel coast and near-stop hover takeover

Date: 2026-11-03. Status: Accepted.

## Decision

The owner clarified that hover should wait until the spin is almost stopped.
Replace the relative release-speed threshold from the carousel ADR-344 with a
token-defined absolute threshold of .08 track units per second, just above the
existing .04 stopping threshold. Queue hover steering until that speed is reached.

Reduce the exponential spin-friction token from 4.6 to 1.2 per second. A typical
release now coasts for roughly three seconds, and a maximum-speed release for
roughly four seconds, before hover takeover or alignment. Stronger spins last
longer. Preserve additive drags, braking, the speed cap and immediate parking for
reduced motion, dialogs, keyboard focus and hidden pages.
