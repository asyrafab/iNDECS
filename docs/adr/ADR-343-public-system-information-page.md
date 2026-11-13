# ADR-343: A dedicated public information page

Date: 2026-11-03. Status: Accepted.

## Context

The owner requested that Ketahui lebih lanjut open a new page containing system
information, instead of expanding the small welcome-page panel.

## Decision

Link the handwritten invitation to the public `/tentang-indecs` route, named
`about`. Navigate normally in the same browser tab. The page is readable without
authentication or JavaScript, with a contents navigation and responsive sections
covering purpose, users, the four departments and application codes, related
applications, workflow principles, spatial/document context, current availability,
cooperating systems, human decisions, logo meaning, access guidance and FAQs.

Use current repository capability evidence rather than treating the design vision
as shipped functionality. Explicitly retain the unavailability of public submission,
payments and tracking, the staged departmental rollout, and the planned AIRS and
E-Plan integrations. Do not expose internal deployment/security implementation,
private records, invented contacts, fee promises or fixed approval timelines.

Reuse public branding, tokens, cards, badges, controls and typography. Add a narrow
`.about-*` class layer for the information layout in indecs.css. This information
page scrolls naturally; the welcome page remains a single viewport. Provide working
home, public access, registration, email-access and official MBSP links.

## Consequences

This replaces only the learn-more accordion. Tracking and the login dialog retain
their existing behaviour. Content stays factual about availability and distinguishes
planned integrations from working access routes.
