# ADR-333: Centre the wordmark and slow its fade

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested centring iNDECS across the complete symbol, from the first
outlined square to the teal square, and a slower text fade.

## Decision

Anchor the SVG wordmark at x=60 with text-anchor=middle, the midpoint of the
symbol's x=0 to x=120 outer bounds. This is a brand lockup alignment, not body
copy. Keep the baseline and type tokens unchanged. Fade text from 58% to 76%
of the eight-second timeline (4.64–6.08 seconds), retaining the existing start
after the visual transition and extending the fade to 1.44 seconds.

## Consequences

The wordmark is centred beneath the entire mark and appears more gently.
Static, skip and reduced-motion states show the final wordmark immediately.
