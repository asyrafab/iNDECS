# ADR-401: Capsule review tool with isolated expansion

Date: 2026-11-18. Status: accepted under the owner request.

Use a bright yellow attention surface with black text/outline through tokens. The collapsed floating tool is a capsule with semicircular ends; explicit expansion morphs its height and corner radius into the existing detailed card. Use native disclosure semantics with progressive CSS size interpolation, duration/easing tokens and reduced-motion support; unsupported browsers retain an immediate working disclosure.

Fix the collapse side effect: the tool is absolutely positioned inside a stable, sticky rail sized for the collapsed tool. Expansion no longer changes the rail or the review-wide offset used by the plan, divider or finding anchors. Observe only the workspace header for header offset; tool resizing remains local to its bounds. Keep dragging, unresolved navigation, scoped score semantics, comments, document source and native no-JavaScript access intact.
