# ADR-345: Centred application chooser and shared popup motion

Date: 2026-11-03. Status: Accepted by programme owner.

The owner requests centred text and icons throughout the application chooser,
an explicit alignment exception to the default flush-left interface. Remove the
visible Buka aplikasi and Belum tersedia labels. Existing authorised tiles remain
links; forthcoming tiles remain non-interactive and retain their muted surfaces.

Slightly enlarge tiles on hover using a shared token, suppressing this motion
under reduced-motion preferences. Reuse the login popup's trigger-origin overshoot
keyframes, duration, easing and frosted backdrop. Measure the chooser against the
Pilih aplikasi trigger before each opening. Preserve close, Escape and focus return.

The homepage Log keluar action uses the existing secondary button styling and
Lucide logout icon, retaining its CSRF-protected POST form.
