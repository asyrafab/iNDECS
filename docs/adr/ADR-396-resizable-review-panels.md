# ADR-396: Resizable plan and technical-comment panels

Date: 2026-11-18. Status: resize behaviour accepted under the owner's request. The focus-mode proposal below remains a recommendation.

Add a keyboard-accessible vertical separator between the existing plan and comment panels on wide screens. Drag with mouse/pen/touch, use Left/Right in 2% steps, Home/End for readable limits, and Enter/double-click or the reset button for the default split. Both panels retain a token-defined minimum readable width; smaller layouts stack and hide the separator. No document iframe is reloaded, comment cleared or form submitted while resizing.

Remember only a numeric layout ratio in a user-scoped localStorage key, with a default fallback when storage is unavailable or invalid. Never save document URLs, application details or comments there. Container resizing/sidebar changes clamp the displayed split without overwriting the preference. Pointer cancellation, Escape, window blur and hidden pages cancel a drag. Temporarily suppress iframe pointer events during a drag so the native PDF viewer cannot capture it. Update separator ARIA bounds and value text, preserve keyboard focus and respect the existing token-only themes.

## Recommended solution to a crowded review page

Provide an optional focused review mode: a compact application identity/status strip, one active finding beside the adjustable plan, and a small pending/attention queue. Keep reasons, source evidence and officer assessment available for the active finding; place detailed background, extracted profile, earlier checks, score diagnostics and comment history behind clearly named disclosures or tabs. Keep AI explanation distinct and initially collapsed in focus mode. Saving and moving to the next finding should be one explicit, authorised action with validation preserving the current input; navigation must never imply saving.

Retain the complete current record view for overview/history and the full application background requested under ADR-395. Do not introduce a new workflow status, hidden-findings rule, bulk confirmation, automatic decision or permission change. The focus-mode arrangement and save/next flow are proposed for owner review, not delivered by the resize increment.
