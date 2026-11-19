# NADI — Navigation, Analysis, Decisions & Intelligence

Date: 9 November 2026. Status: product name, direction and functional boundary confirmed by the owner. Technical implementation remains to be designed.

## Purpose

NADI is an advanced application used within a meeting: a shared working environment for navigating papers, maps and evidence, analysing issues, supporting discussion, and capturing authorised decisions and actions. Scheduling, paper administration and minutes support that experience; ordinary meeting administration does not define the product.

- **Navigation:** move through the agenda, papers, plans, maps and supporting evidence.
- **Analysis:** examine relevant information, compare evidence and understand issues during discussion.
- **Decisions:** capture the authorised person's outcomes, conditions and assigned actions with traceable history.
- **Intelligence:** bring relevant context and evidence-backed insights into the meeting. The name does not imply that AI features are already implemented.

## Product and module boundary

| Part | Responsibility |
|---|---|
| **eOSC within iNDECS** | Register each paper for its assigned meeting, associate the relevant application and documents, and support the preparation/assignment workflow. Use eOSC for this registration context; iNDECS communicates with NADI for its eOSC module. |
| **NADI** | The application used within the meeting: agenda navigation, presentation, paper/plan viewing, shared meeting context, discussion support, evidence and maps where relevant, authorised decisions and meeting outputs. |
| **NADI > eOSC** | First specialised module, serving Jawatankuasa OSC meetings with development applications, spatial context, technical comments, proposed decisions, approval conditions and the established officer/chair sequence. |
| **Future meeting modules** | Extension opportunity only. Define each when a real committee or meeting needs it; no additional module or workflow is committed now. |

The confirmed hierarchy is **NADI > eOSC**, alongside **NADI > other meeting types** when needed. eOSC names both the iNDECS registration context and the OSC meeting module within NADI. iNDECS communicates with NADI and registers/assigns papers under eOSC. The committee remains Jawatankuasa OSC.

One NADI application can host meeting-specific modules. eOSC does not define the entire product. NADI is distinct from URBAN, the existing internal spatial map application; using spatial context does not rename or absorb URBAN.

## Intended eOSC journey

Navigation clarification (ADR-410, 19 November 2026): iNDECS labels the existing
registration workspace **Daftar Mesyuarat** under **Ruang kerja**. Its `osc.*`
routes and access rules remain unchanged. NADI is listed separately as the
meeting application, unavailable until a launch destination is configured.

1. Officers register each paper in eOSC within iNDECS and assign it to the intended meeting, including papers without a linked departmental application where permitted.
2. The secretary prepares and publishes the meeting agenda through the agreed preparation workflow. Exact ownership of agenda ordering/publishing screens is to be reconciled with the existing implementation.
3. NADI opens the assigned eOSC meeting and its authorised, versioned papers; presenters and members work within the meeting context. They do not re-register the same papers in NADI.
4. In the eOSC module, officers present the paper, plan and map, members consider the evidence, and the presenting or assigned officer records the proposal before the chair gives the decision.
5. Decisions, conditions, notes and actions retain actor attribution and audit history. Official OSC outcomes are reflected in iNDECS through an agreed, validated integration; officers do not manually re-enter competing decisions.
6. Minutes and follow-up outputs derive from the captured meeting record and remain subject to the existing human verification and release controls.

This describes target behaviour, not a currently working NADI integration.

## Shared foundation and OSC-specific rules

The reusable foundation may support meeting context, ordered agenda items, permissioned papers, presentation/following, participants, attributed notes, decisions, actions and audit history. Keep OSC rules in the eOSC module: linked development applications, site maps, technical-agency input, one agenda item per application, the four OSC outcomes, officer-before-chair sequence and signed-plan/release requirements.

Other meeting types must not be forced to have development applications, spatial data, OSC outcomes or an OSC chair workflow. Do not build a speculative general-purpose workflow engine or additional modules just to support possible future use.

## Integration and authority

- iNDECS continues to own official development-control applications, authoritative document versions, paper registration/meeting assignment, identity and official workflow, decisions and letters. NADI is the meeting interaction and authorised decision-capture surface; record ownership and write-back require an explicit integration design.
- Maintain stable references between meeting, agenda item, paper/version, linked application, actor and captured outcome. Avoid duplicate registration and competing official records. Define version/freshness handling, authorised retrieval, acknowledgement, retries and reconciliation before implementing integration.
- Use existing staff identity and permissions where applicable. Define external attendee access, expiry and per-item scope explicitly. NADI is not permission to introduce a second account directory or bypass iNDECS access checks.
- Preserve append-only audit, decision immutability/correction history, officer-before-chair controls, verified minutes and signature/release gates for OSC.
- Preserve the meeting's resilience to unavailable ArcGIS/video services. Spatial services support the OSC experience; GIS is not mandatory for every meeting type.
- AMIRA, MATRIX and MINDA retain their documented roles and access boundaries. AI suggests; authorised humans decide. Their NADI integrations remain proposals.

## Existing implementation and transition

The 29 October design and Plans 1–2 describe the historical eOSC implementation inside iNDECS. Reuse that work where appropriate, but reconcile it against this boundary before continuing the live-meeting build. This direction supersedes the assumption that the entire live meeting experience must remain branded eOSC within C10. It does not remove iNDECS paper registration or automatically extract existing code.

Keep current repository paths, `/osc` routes, `osc.*` permissions, `core.osc_*` tables, class names, assets and branch names intact until a reviewed migration plan exists. The folder name `eOSC+` and canonical documentation location `iNDECS/docs/eosc/` remain for link compatibility. NADI is the new product name, not a blanket replacement of every historical or technical eOSC reference.

## Open implementation decisions

- NADI runtime/repository, hosting, deployment, navigation entry and relationship to the current Laravel application.
- Identity/session integration and external attendee access.
- Ownership of meeting definitions, agenda preparation/publication and meeting execution state across the two products.
- Versioned paper/agenda exchange, access checks, document freshness and local meeting availability.
- Authoritative meeting capture, decision acknowledgement/write-back, failure recovery and audit reconciliation with iNDECS.
- Migration/reuse of the built agenda/map screens, existing data, permissions and temporary eOSC artwork; NADI visual identity.

No runtime, database, route, logo or deployment changes are made by this documentation decision.
