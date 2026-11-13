# ADR-371: Adopt department icons in navigation

> **NADI alignment — 9 November 2026:** [Current direction](../eosc/nadi-direction.md): **NADI > eOSC**. iNDECS registers papers under eOSC for assigned meetings and communicates with NADI, the application used within meetings. Other meeting types may become sibling NADI modules later. The committee remains Jawatankuasa OSC. Earlier all-inside-iNDECS meeting descriptions are historical where they conflict; integration implementation remains to be designed.

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

Following review of the ADR-370 assets, the owner authorised the next step:
apply the approved department artwork to the existing application chooser and
staff workspace navigation. Use detailed 64 px artwork in chooser tiles and
compact 32 px artwork in the workspace picker and contextual topbar.

The launcher's permission-filtered descriptors carry an explicit department key.
Only departmental entries use the new artwork. Public/system, eOSC+, AIRS and
spatial entries keep their existing identities. The main iNDECS brand, sidebar
brand and footer retain the three-square mark and common interactions stay teal.
Action icons remain Lucide. Do not create new departmental routes or access.
Kejuruteraan and Landskap remain unavailable cards shown only where already
permitted; introducing their icons does not activate the modules.

Keep existing route destinations, access checks, current-page semantics, dialog
focus behaviour and no-JavaScript fallback. Department art next to a visible
name is decorative. Avoid a second rounded tinted container around the square
artwork. The original SVG preview remains available for asset review.

## Verification

Run the existing application-launcher and department-navigation access tests on
PostgreSQL. Render actual Blade output for chooser and staff shells; check light,
dark, desktop and narrow-phone layouts. Compile Blade and verify the icon assets
load. This changes presentation and descriptor metadata only.
