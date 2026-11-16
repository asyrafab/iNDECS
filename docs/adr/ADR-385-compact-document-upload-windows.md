# ADR-385: Compact document upload windows

Date: 2026-11-16

Status: Accepted (owner request)

## Decision

The public draft upload checklist is a raised, compact list of document names
with semantic Lucide status icons. Descriptions, required/optional information,
uploaded file details and upload/confirmation controls appear in a native dialog
when a document is selected. Unsorted uploads use the same compact presentation,
with sorting and removal controls inside their own dialog.

The common visible status vocabulary is Belum dikemukakan, Sedang dibaca and
Dilampirkan. Unreadable files and files needing applicant confirmation retain
distinct warning states. Icons have accessible text and tooltips, supported by
a short legend. No review or readiness rules change.

Use existing portal dialog keyboard, Escape, backdrop and return-focus behaviour.
Dialogs must work after a checklist polling replacement. Polling pauses while a
dialog is open or a file is selected, preserving applicant input. Without
JavaScript, document links lead to the expanded details in normal document flow.
All styling uses shared tokens in indecs.css and works in both themes and on
small screens.
