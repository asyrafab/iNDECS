# ADR-335: A simple welcome entrance

Date: 2026-11-03. Status: Accepted.

## Context

The owner approved the welcome concept and requested three entry actions,
handwriting for “Ketahui lebih lanjut”, authentic branding and photographic
layers which come forward to show the full image when hovered.

## Decision

Replace the guest service catalogue with a full-height welcome composition.
Use the supplied MBSP SVG followed by a rule and the existing iNDECS mark.
Keep exactly three entry actions: Semak Permohonan, Log Masuk and Ketahui lebih
lanjut. Native details surfaces provide tracking availability, public/staff
login choices and an introduction without adding navigation to the welcome.
Public tracking stays explicitly unavailable; no pretend lookup is added.

The existing finite architectural introduction runs before the welcome, without
skip, Escape dismissal or replay. Reduced-motion and no-JavaScript visitors see
the welcome immediately. Failure to load introduction textures must also reveal
the welcome. Background content is inert only while the introduction runs.

Layer real stock photographs of building, earthwork, landscape and a plan on
the right, retaining natural colours. Hover, keyboard focus and touch selection
bring each complete image above its siblings. The handwriting font is a narrow
owner-approved exception for the learn-more invitation, defined through tokens.
Keep all other typography in Google Sans and use token-only theme styling.

## Consequences

The first screen offers a simple choice. Existing authentication and authorised
dashboard routing remain intact. Stock imagery is illustrative, not evidence of
an MBSP project; sources are recorded with the assets.
