# ADR-373: Esri logo for E-Plan Submission

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

Use Esri's original full globe-and-wordmark SVG on the E-Plan Submission chooser
entry, as requested by the owner. Copy the unchanged asset served by Esri's
official global navigation. Retain the logo in its entirety, original black
colour and aspect ratio; do not generate an approximation or crop the globe.

Provide a token-based fixed white identity ground so the unmodified black logo
is legible in both themes. Use a wider 112 × 64 px identity area with token-based
inset and a neutral border. This is a provider identity exception, not a new UI
accent. The Esri logo identifies the spatial subsystem's provider; the entry
continues to be named E-Plan Submission.

Use an explicit brand key in the existing launcher descriptor and whitelist
brand asset paths in the view. The adjacent entry name labels the tile, so the
logo is decorative. Preserve its current administrator visibility and null URL:
this does not activate the planned spatial application or add an outbound link.

Provenance and Esri attribution are recorded beside the copied SVG. Esri's brand
guidance requests logos remain intact and unmodified; no legal or licensing
agreement is entered into by this change.
