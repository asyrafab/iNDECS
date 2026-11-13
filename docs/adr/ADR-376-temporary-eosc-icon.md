# ADR-376: Temporary eOSC+ icon

> **NADI alignment — 9 November 2026:** [Current direction](../eosc/nadi-direction.md): **NADI > eOSC**. iNDECS registers papers under eOSC for assigned meetings and communicates with NADI, the application used within meetings. Other meeting types may become sibling NADI modules later. The committee remains Jawatankuasa OSC. Earlier all-inside-iNDECS meeting descriptions are historical where they conflict; integration implementation remains to be designed.

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

Use the owner's supplied globe-and-teal-plus PNG as the temporary eOSC+
identity. Copy the source unchanged to public/img/eosc-temporary.png, retaining
its alpha transparency, colours, proportions and full artwork. This temporary
identity is an owner-requested exception for supplied photographic artwork;
do not tint, redraw or replace its background.

Use the 64 px identity area in the application chooser. A reusable x-eosc-icon
component provides compact 32 px artwork in the workspace switcher, eOSC+
cross-workspace navigation and OSC contextual header. Keep the main iNDECS
brand/footer and functional Lucide action icons unchanged. Visible adjacent
labels identify the artwork, so the image is decorative by default.

This does not change meeting permissions, route destinations or module status.
Record that the asset is temporary and supplied by the owner; its eventual
replacement should not require changing callers of the component.
