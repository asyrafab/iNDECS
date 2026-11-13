# ADR-375: E-Plan Submission destination

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

The owner supplied https://spgis.mbsp.gov.my/ePlanSubmission as the destination
for the E-Plan Submission chooser tile. Replace its null URL with that exact
HTTPS address. Use the existing native anchor behaviour in the same tab.

Retain the current administrator visibility rule, Esri artwork and tile name.
This is an outbound navigation link only: do not forward credentials, tokens,
record data or query parameters, and do not imply SSO or API integration exists.
Kejuruteraan and Landskap remain unlinked planned entries.
