# ADR-399: Collapsible floating review task tool

Date: 2026-11-18. Status: accepted under the owner request.

Refine ADR-398 with a native details/summary tool, collapsed by default to a short top-right strip. Keep saved progress and the advisory score visible in the strip; expand explicitly for task state, remaining work and score caveats. Use a Lucide task icon, soft teal surface, accent border and raised shadow to distinguish the floating tool from record sections. Preserve native keyboard activation and no-JavaScript operation. Do not store application data or comments as a layout preference.

Measure height after disclosure changes so the sticky plan and finding anchors reclaim space when collapsed. Expansion never saves comments, changes scores, reloads the plan or changes workflow. The full score retains its deferred/unavailable states, and completion remains saved-comment progress only.
