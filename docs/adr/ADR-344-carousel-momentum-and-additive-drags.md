# ADR-344: Protected carousel momentum and additive drags

Date: 2026-11-03. Status: Accepted.

## Decision

After release, hover cannot override the spin until its speed falls to 80% of
that release's speed. Remember pointer steering during this interval and apply
the latest position once the threshold is crossed. Leaving the gallery clears
steering but preserves the inertial tail and final alignment.

A new captured drag retains the existing spin as a decaying carry velocity.
On release, add the gesture velocity to that carry: repeated strokes in the same
direction accelerate the carousel; opposite strokes brake or reverse it. Clamp
the combined speed to the existing spin maximum. A click without movement
preserves the carry; cancellation clears it. Reduced motion, focus, dialogs and
hidden-page interruption retain their existing immediate parking behaviour.

This supersedes ADR-341's immediate hover interruption and replacement of spin
velocity by each new gesture. The 80% threshold means remaining speed, rather
than an 80% loss of speed.
