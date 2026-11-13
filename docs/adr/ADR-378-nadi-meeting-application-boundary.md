# ADR-378: NADI identity and in-meeting application boundary

Date: 2026-11-09. Status: accepted by owner for product naming, scope and functional boundary; implementation topology remains open.

## Context

The name eOSC ties the live meeting experience to OSC and conflates paper registration with the application used within the meeting. The owner confirms that eOSC within iNDECS registers each paper for its assigned meeting and that one application is needed for use within the meeting. URBAN already names the internal spatial map application.

## Decision

Naming clarification confirmed by owner on 9 November 2026: use **eOSC** for both the former eOSC+ registration label and the previously named OSC module. The hierarchy is **NADI > eOSC**, with **NADI > other meeting types** later. iNDECS communicates with NADI and registers papers under eOSC for the assigned meeting. The committee name Jawatankuasa OSC is unchanged.

Adopt **NADI — Navigation, Analysis, Decisions & Intelligence** for the advanced in-meeting application. eOSC is its first specialised module. Keep room for other meeting types without committing speculative modules or applying OSC-specific rules to all meetings.

Retain eOSC within iNDECS for paper registration and meeting assignment. NADI uses that meeting/paper context for presentation, evidence/map navigation, analysis, discussion and authorised decision capture. Do not duplicate paper registration or official application decisions.

This supersedes the product-boundary assumption in ADR-213 that the entire live-meeting experience must remain eOSC inside C10. It does not supersede the continued iNDECS paper-registration role, existing OSC decision authority, security, audit or release controls. Existing implementation, routes and schema remain until a reviewed transition is designed.

## Consequences and open decisions

Follow [NADI direction](../eosc/nadi-direction.md) for the intended journey and boundary. Reconcile Plans 1–2 before further meeting implementation. Decide hosting/repository, identity, meeting/agenda state ownership, document exchange, authorised decision write-back, audit/retry/reconciliation and migration explicitly. No separate database, deployment, code extraction, UI rename or logo replacement is authorised by this documentation decision alone. iNDECS remains the official development-control record/workflow authority; humans decide and AI only assists.
