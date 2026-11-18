# ADR-405: Remember floating-tool layout between saved findings

Date: 2026-11-18. Status: accepted under the owner request.

Supersede ADR-400's page-local-only position boundary for layout preferences only. Store a versioned per-officer browser preference containing the open/closed boolean and numeric viewport position (or null for the default corner). Restore it after full-page save/next navigation and reload; clamp it below the header and within the current workspace/viewport. Disable the restore animation so the remembered state is immediately usable.

Persist committed pointer/keyboard moves and disclosure changes, with a final preference snapshot on comment-form submission. Cancelled drags must not overwrite saved position. Validate stored shapes/numbers and tolerate missing, malformed or blocked storage with a working default. Keep officers' preference keys separate; do not store application identifiers, scores, statuses, comments, evidence, document URLs or record data. Actual score/task presentation remains server-derived. No workflow or server storage changes.
