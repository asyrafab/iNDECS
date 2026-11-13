# ADR-365: AMIRA fluid avatar and random bubble release

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

Refine ADR-361's avatar using the approved visual concept: an uneven fluid teal
body, faint mist behind it, one tilted orbiting sound-wave line, and small
bubbles that bud from the surface, drift away and pop at independent intervals.
Keep all gradients, mist and glow inside the existing avatar canvas exception.
The orbit weaves behind and in front of the body; response activity increases
its amplitude and extends a short wave toward the conversation below.

Mood controls the silhouette and rhythm through the existing five mood states.
Preserve the token-based mood palettes and `AmiraBubble.setMood/setSpeaking`
interface. Local guidance triggers a brief response animation; it does not
represent audio playback or a live AI connection. Availability labels stay intact.

Use a larger token-sized desktop avatar. Bound satellite count and lifetime;
omit satellites on the compact phone-bar avatar. Stop animation when hidden or
offscreen. OS and application reduced-motion settings render a still frame with
no bubble release, popping or response animation. No JavaScript/canvas retains
the existing CSS fallback. New duration and size values live in tokens.css.

## Boundaries

This changes presentation only: no model requests, new remote assets, record
changes, audio capture, permissions or workflow changes.
