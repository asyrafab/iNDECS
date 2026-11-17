# Officer technical comments

Decision: [ADR-394](../adr/ADR-394-officer-technical-comment-workspace.md). Applies to the LJUP `/kejuruteraan` officer application page.

The completed check shows compact native finding disclosures and exact counts of all findings, findings with a saved officer assessment, and findings not yet assessed. A saved assessment is not a compliance decision. Search and the section/review filters act only on the current page and start with all findings included. Perlu perhatian uses the latest officer status if available, otherwise the AI status; it includes all statuses other than Ditunaikan/Tidak berkaitan. No score or acceptance gate is introduced.

Open a finding to compare document/page links and quotes, the labelled AI explanation/confidence, and the officer assessment. Choose an explicit officer status for a new assessment. The latest saved officer comment is prefilled for a new history entry. Standard wording may be inserted by an explicit button and edited before saving. History lists newest entries first with officer and time.

Each save uses the existing authorised append-only comment endpoint and returns to the relevant finding. JavaScript reopens that row from its URL fragment. Earlier checks remain read-only. Evidence still opens the existing scoped private document endpoint in a new tab.

Unsaved comment/status edits are marked, kept visible when filtering, protected from the list-collapse button and included in the browser navigation warning. The next-unreviewed action opens another visible unreviewed finding without saving. Nothing is autosaved or stored in browser storage. If several comments are being edited, saving one navigates away and warns about the others; finish or copy those comments before continuing. Validation errors retain the failed finding's own input. Cancelled navigation retains protection for every edited row.

Without JavaScript all findings, source links, comment forms and native histories remain available. JS-only controls remain hidden. On phones the evidence and officer panels stack; the extracted-profile table scrolls inside its own keyboard-accessible region. Theme differences remain token-only.

Implementation: `partials/technical-review.blade.php`, `partials/feedback.blade.php`, `public/js/officer-review.js` and the shared `.technical-*` classes in `indecs.css`. No new dependencies, migration, permissions, official decisions or applicant visibility are added.

Verification includes the existing officer access/comment suites, targeted server-rendering tests for progress/prefill/scoped validation/read-only history, five JavaScript interaction tests, and synthetic real-browser checks of filtering, unsaved edits, next-finding navigation and desktop/mobile layouts. The separate department-database standardisation edits already in the workspace are not part of this UI increment.

Local results on 2026-11-17: the full workspace PostgreSQL regression passed 878 tests (876 passed, two skipped), with 4,353 assertions and zero failures. All 28 JavaScript tests, targeted PHP formatting and production frontend build pass. Real-browser checks confirm search/reset, retaining unsaved edits, next-finding navigation, and a 390px mobile viewport with no page overflow. No operational database migration is needed for this increment.
