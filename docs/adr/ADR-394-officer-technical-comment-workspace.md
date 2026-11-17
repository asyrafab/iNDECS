# ADR-394: Officer technical-comment workspace

Date: 2026-11-17. Status: accepted for implementation under the owner's request to improve the officer technical-comment workspace.

Replace the long expanded LJUP finding stream with compact native disclosure rows. Show the rule, section, AI status and latest officer status separately. Provide exact total/reviewed/unreviewed counts, explicit search/section/review-state filters, and navigation to the next visible unreviewed finding. Start with all findings included; filters are officer-selected and never remove records or alter advisory scores.

An expanded finding puts source documents/page references and quotes before a labelled AI explanation, with officer assessment in a distinct adjacent panel on wide screens and below on narrow screens. Existing finding comments/history stay attributed and append-only. Earlier checks remain historical/read-only; only the handed-over final check offers comment forms to existing authorised reviewers.

The officer selects an explicit status for a previously unreviewed finding. Later forms prefill the latest saved officer assessment, while validation errors retain input for the particular finding. A supplied standard comment may be inserted by an explicit officer action; it is editable and never submitted automatically. Draft edits stay in the page memory only. Flag unsaved edits, retain edited rows through filtering/collapse actions and warn before navigation away; do not store sensitive comments in browser storage.

All findings and native disclosures remain usable without JavaScript. JS-only controls are hidden until initialised. Saved finding URL fragments reopen the correct row and any historical check parent. Search/filter counts announce results; empty results provide a reset action. All UI strings are BM, icons are existing Lucide symbols, and layout/type/status/focus use shared tokens and classes in indecs.css. No dependency, migration, permission, AIRS decision logic, applicant visibility or official workflow change is introduced.
