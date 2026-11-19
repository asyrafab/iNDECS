# ADR-411: Meeting registration preserves departmental navigation

Date: 2026-11-19

Status: Accepted at the owner's request.

Remove the redundant Semua aplikasi shortcut from Ruang jabatan. Its complete
application list remains available with existing disabled states and motion.

Remember the last authorised departmental workspace in the signed-in session,
keyed by user ID. Shared meeting-registration pages retain that department's
identity and navigation while marking Daftar Mesyuarat active. Revalidate the
remembered department against current access on every render; a preference never
grants permission. Direct meeting links use the user's authorised department or
another authorised workspace as fallback. Meeting-only users receive no invented
departmental links. Store only the department identifier, not a URL or record ID.
Existing `osc.*` routes, guards and meeting/agenda operations remain unchanged.
