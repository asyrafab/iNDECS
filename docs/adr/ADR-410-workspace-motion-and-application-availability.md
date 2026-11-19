# ADR-410: Workspace motion, application availability and meeting registration

Date: 2026-11-19

Status: Accepted at the owner's request.

Supersedes ADR-409's hidden unauthorised/current application entries and NADI
registration shortcut. Active staff see all four departments and cooperating
applications. Authorised destinations link normally; inaccessible entries are
grey, labelled as disabled and have no URL. Existing route guards remain binding.
The public chooser remains the public portal. No new permission is granted.

NADI is the separate in-meeting application described by ADR-378. No NADI launch
URL is configured, so its application entry is unavailable. The existing `osc.*`
registration functionality becomes **Ruang kerja > Daftar Mesyuarat**, using its
existing permissions/routes, without renaming technical identifiers or changing
meeting/agenda behaviour. A distinct registration entry in the homepage chooser
keeps meeting-only staff able to enter their workspace.

The native Ruang jabatan disclosure has token-based hover feedback and measured
expand/collapse motion. Rapid reversals retain the current animated height.
Keyboard/native fallback, reduced-motion settings and OS preferences remain
supported; unavailable entries never become links during animation.
