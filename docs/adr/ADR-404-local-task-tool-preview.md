# ADR-404: Local display preview for task colour and status

Date: 2026-11-18. Status: retired after the owner requested removal of testing controls.

In local/testing environments, add an expandable Ujian paparan control inside the floating tool. Independently preview neutral/red/yellow/green and sample task labels; mark overrides as Ujian in the compact header. Return to recorded presentation through Paparan sebenar or the original select options. Production and other environments do not render these controls.

Preview changes only this tool's DOM colour and task label. Do not change the score, progress counts, application status, finding assessments, controls elsewhere, browser storage or server data. Use no request or automatic save. Hide the controls until the existing tool script initializes. Reload restores recorded presentation. Preserve dragging, disclosure, unresolved navigation, unsaved comments and stable review layout.

The temporary selector, override badge, client-side overrides and preview-only styling are removed in every environment. The existing server-derived score, score band, task label and saved progress are now the sole presentation source. Earlier paragraphs record the retired test increment.
