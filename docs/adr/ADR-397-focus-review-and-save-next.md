# ADR-397: Focus Review and save comment / next finding

Date: 2026-11-18. Status: accepted under the owner's instruction to implement Focus Review.

Focus Review is the default officer page, with a compact application identity/status heading, expandable recorded background and diagnostics, the resizable plan, and exactly one active finding. A compact filtered queue supplies explicit previous/next and direct finding selection. The complete overview remains available through an explicit mode link. AI explanations, extracted profile, earlier checks, history and internal recommendations remain accessible, with AI explanations initially collapsed in focus mode. No findings are removed or excluded by scores.

The primary action is `Simpan ulasan & seterusnya`. Use the existing authorised, attributed append-only comment service. Validate first and navigate only after a successful save. Determine the next unreviewed finding server-side from this same check's report order and actual scoped findings/feedback, wrapping to earlier pending findings. Ignore arbitrary client return URLs/next IDs. If none remain, show a review-completion message and access to internal recommendations; do not approve, close or otherwise transition the application.

Failed validation retains the current finding, status and comment. A failed-input finding takes priority over a stale URL fragment. Navigation and queue selection alone never save, clear or persist comments; edited values stay in the page and are identified in the queue, with the existing departure warning protecting edits to other findings. Preserve evidence navigation, original check documents, role/district scope, historical read-only behavior and the user-scoped panel split. Native forms/disclosures remain available without JavaScript; save/next redirects include a scoped finding query/fragment to reopen the server-rendered target.

Mode is an explicit safe UI query (`review=focus|overview`), not a permission or workflow status. No migration, AI call, automatic assessment, public visibility or official letter issuance is introduced.
