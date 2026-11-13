# ADR-356: Google-branded sign-in button

Date: 2026-11-04. Status: Accepted. Refines ADR-355.

## Decision

The owner requests the familiar Google sign-in appearance. Use Google's official locally stored multicolour G, an outlined white surface, dark Google Sans medium label and centred button text. This provider identity is an explicit exception to Lucide-only interface icons, left-aligned button labels and the single-accent rule; it does not introduce a second application accent. The logo is unchanged, decorative and never recoloured or filtered.

All button colours, sizing and spacing use dedicated/shared CSS tokens; the light provider treatment remains consistent in both themes without component theme branches. Keep the existing OAuth route and public-only availability checks. The downloaded logo source is recorded beside the asset. No authentication behaviour changes.

Reference: [Google sign-in branding guidelines](https://developers.google.com/identity/branding-guidelines).
