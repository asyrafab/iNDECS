# Resizable officer review panels

Decision: [ADR-396](../adr/ADR-396-resizable-review-panels.md). No migration, dependency or permission change.

On a wide review page, drag the grip between the plan and comment panels. The panel split keeps each pane at least the token-defined minimum width. The focused separator also accepts Left/Right (2% steps), Home/End (readable limits), and Enter or double-click (default). `Tetapan lebar asal` resets the split to the default 55% plan share, subject to the available width.

The numeric preference is saved under `indecs.technical-review.split.<user-id>` in localStorage. No application/document identifiers, URLs or comments are saved. Disabled/invalid storage uses the default. Container/sidebar changes temporarily clamp the split without replacing the saved preference. On narrow screens the panes stack and the divider/reset button are hidden; widening restores the preference. Without JavaScript the existing fixed split/stacked layout remains usable.

Dragging uses pointer capture and temporarily disables iframe pointer events. Resizing does not replace the iframe, change its source, submit any form or clear comments. Escape, pointer cancellation, window blur or a hidden page cancel the drag and restore the previous split. A cancelled drag is not persisted.

Implementation: `officer-panel-split.js`, `.technical-panel-divider` and `.technical-review-desk.is-resizable` in `indecs.css`, with `--size-technical-pane-min` and `--ratio-technical-plan-default` in `tokens.css`. Separator ARIA values describe the plan/comment percentages.

Verification: all 37 JavaScript tests pass, including five new tests for keyboard/pointer boundaries, cancellation, narrow layouts, per-user persistence and blocked/invalid storage. Seven affected PostgreSQL page tests pass (61 assertions), and the production build/whitespace checks pass. An isolated real-browser test dragged the plan to 70%, verified unchanged comment text, unchanged iframe identity/source and zero iframe reloads, then checked keyboard resize/reset, a 390px mobile layout and preference restoration after refresh. Synthetic records and a synthetic PDF were used. The full PHP suite was not rerun for this frontend-only increment.

The crowded-page focus-mode recommendation is recorded in ADR-396 as a proposal, not implemented in this increment: compact identity/status strip, one active finding beside the plan, a navigable finding queue, optional background/AI/history details, and an explicit save-and-next workflow. The complete overview remains available.
