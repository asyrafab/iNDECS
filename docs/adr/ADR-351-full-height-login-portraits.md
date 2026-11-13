# ADR-351: Full-height borderless login portraits

Date: 2026-11-04. Status: Accepted.

Remove portrait inset margins and its independent corner rounding. On desktop,
stretch the image edge to edge down the right side of the login popup, using
object-fit cropping if form content grows. Keep the popup's outer rounding and
shadow; remove its border so the photograph reaches the outer edge. The initial
portrait column remains 2:5. Narrow screens retain the readable stacked layout.

Replace the public portrait with a Chinese woman and a helmeted architect in an
office interior. Replace staff with distinct office/site officers at a construction
site, both facing left toward the form. All four subjects have distinct identities.
Store new v2 assets and prompts locally; retain originals as unused prior versions.
This supersedes ADR-350's inset photograph treatment and initial people/backgrounds.

## Owner clarification — 2026-11-04

Only the staff bodies face left. Their heads turn toward the camera, with direct
eye contact and warm smiles. Use the edited `staff-users-v3.png`, preserving the
same people, clothing, site and body poses from v2. Public remains v2.
