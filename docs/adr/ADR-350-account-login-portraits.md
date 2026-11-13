# ADR-350: Account-specific login portraits

Date: 2026-11-04. Status: Accepted.

## Decision

Add a 2:5 photographic panel to the right of the welcome login content, selected
by the existing public/staff switch. Public shows a casually dressed woman and
a male architect in a yellow helmet holding a rolled plan. Staff shows an office
officer and a site officer. Both generated Malaysian pairs smile toward the viewer.
These are illustrative people, not identified MBSP employees.

Keep the images untinted and local, with prompts and generation provenance in
`public/img/login/README.md`. Render both assets server-side and toggle their
hidden states with the account panels, including validation-error reopening.
Images are decorative and use empty alt text; user type remains communicated by
the accessible selector. Preserve authentication endpoints, focus management and
account palettes. At narrow widths, move the portrait below the form at the same
aspect ratio so controls remain usable. The dialog may scroll within its viewport.
