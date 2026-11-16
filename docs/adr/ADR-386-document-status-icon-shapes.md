# ADR-386: Compact shaped document status icons

Date: 2026-11-16

Status: Accepted (owner request)

## Decision

Refine ADR-385 with smaller status icons: a dark green circle with a check for
Dilampirkan; a grey square with three vertical dots appearing cumulatively in a
loop for Sedang dibaca; a red circle with an X for Belum dikemukakan; and an
orange triangle with an exclamation for Untuk perhatian. Unreadable documents
also use the attention shape, retaining their precise accessible description.

The legend includes all four states and sits below the document lists, aligned
to the right. Reuse the existing semantic colour palette through dedicated
tokens, including icon sizing and reading timing. This owner-requested filled
icon treatment supersedes the earlier tinted status-icon styling. Normal badges
and review rules are unchanged. Reduced motion shows all three dots statically.
