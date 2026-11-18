# ADR-400: Movable review tool, unresolved navigation and advisory colours

Date: 2026-11-18. Status: accepted under the owner request.

Add an explicit pointer-captured drag handle and keyboard arrow movement, bounded to the visible workspace below its header. Escape/cancellation restores the previous position; a reset action restores the default corner. Re-clamp on disclosure and viewport/header resizing. Keep position in page memory only, never store comments or document data. Preserve the native disclosure and avoid triggering it from the drag handle or navigation action.

Jump to the next unsettled finding of the final/latest displayed check, wrapping in report order. Unsettled means no saved assessment, or a saved assessment other than Ditunaikan/Tidak berkaitan. Navigation never saves, assesses or changes workflow; page-local unsaved edits remain protected.

Use darker semantic tool surfaces from tokens: red below the existing configured advisory threshold (default 50), amber from that threshold to the visual green threshold (default 80), green at/above it. The green boundary is never below the red boundary. Unknown/deferred scores use neutral navy. Colours describe the existing advisory score only, never approval, rejection or completed work; show threshold labels and unresolved count separately. The visual green threshold is UI-only configuration and changes no review engine rule or payment/submission gate.
