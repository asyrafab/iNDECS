# ADR-344: Authorised application chooser after homepage login

> **NADI alignment — 9 November 2026:** [Current direction](../eosc/nadi-direction.md): **NADI > eOSC**. iNDECS registers papers under eOSC for assigned meetings and communicates with NADI, the application used within meetings. Other meeting types may become sibling NADI modules later. The committee remains Jawatankuasa OSC. Earlier all-inside-iNDECS meeting descriptions are historical where they conflict; integration implementation remains to be designed.

Date: 2026-11-03. Status: Accepted by programme owner.

The homepage remains the shared entrance after authentication. Successful homepage
login returns there and opens a native application chooser, with distinct local
Lucide icons and server-side permission filtering. Direct staff login and intended
protected URLs retain their existing destinations. The former root dashboard moves
to `/dashboard`, preserving its route name and authorisation.

Perancang, Bangunan (including legacy-only access), eOSC+ and public account access
link to existing authorised routes. The system administrator also sees forthcoming
Kejuruteraan, Landskap, AIRS and E-Plan entries, explicitly unavailable until their
entry points and integration permissions exist. No invented external URLs or SSO
claims are introduced. The chooser does not grant access; module middleware remains
authoritative. Public accounts never receive staff links.

Staff number 18811 is promoted using the existing `system_admin` role, whose grants
are unscoped across departments and application types. Promotion is an explicit,
audited console operation on an existing staff account, never a login-time ID bypass.
It clears district scoping and preserves credentials and identity linkage.

Use `.application-dialog`, `.application-grid`, `.application-tile` and
`.application-icon` in the shared CSS layer. The chooser opens automatically on the
authenticated homepage, closes with Escape, backdrop or its close control, and can
be reopened with Pilih aplikasi. Native modal focus isolation and explicit focus
return apply. Without JavaScript its application links remain available inline.
