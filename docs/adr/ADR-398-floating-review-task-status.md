# ADR-398: Persistent officer task status and advisory score

Date: 2026-11-18. Status: accepted under the owner request.

Add a compact top-right task summary to the LJUP officer review page, sticky below the workspace header while scrolling. Reserve its own row and offset the sticky plan and finding anchors below it; the transparent outer rail never blocks pointer access to the plan. Show the final check (or latest check when no final check exists), task progress based only on saved officer assessments, and the existing advisory score after officer comments. Label the score as advisory and defer it when profile corrections invalidate it. Pending, failed and absent checks never show a fabricated score or completed task.

Use token-only surfaces and responsive layout. Measure the actual wrapping workspace header and summary height so the sticky plan, divider grip and finding anchors remain below both on desktop and mobile. Keep historical check scores in their existing detail panels. Do not change scores, permissions, workflow, audit records or save behavior. Native server-rendered content remains usable without JavaScript.
