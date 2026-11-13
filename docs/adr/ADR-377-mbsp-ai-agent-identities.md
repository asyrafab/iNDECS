# ADR-377: MBSP AI agent identities and documentation alignment

Date: 2026-11-09. Status: agent naming accepted by owner; integration proposals not approved as implemented architecture.

## Context and decision

The owner confirms AMIRA — AI Municipal Interaction & Response Assistant; MATRIX — MBSP AI for Text Recognition & Information eXtraction; MINDA — MBSP Intelligence Network for Data Analytics. Establish MATRIX as a separate planning project and align the six named project documentation sets.

AMIRA remains application assistance. MATRIX occupies the document/records specialist role. MINDA remains internal institutional intelligence. The ecosystem provides shared agent management/coordination. MATRIX is not a rename or migration of AIRS; AIRS retains its existing technical review/extraction implementation and iNDECS remains the official record/workflow authority.

## Consequences and preserved decisions

No code, endpoints, provider selection, database topology, deployment, business authority or credentials change. ADR-352/353/354/369 and existing free-precheck/payment/accepted-submission controls remain applicable. Technical review, official decisions and scored findings keep their current safeguards. New shared-agent calls require scoped versioned APIs and separate implementation decisions.

See [current roles and project implications](../integration/ai-ecosystem.md). Canonical Bangunan/eOSC documentation is aligned alongside its working mirrors. Existing historical design descriptions are retained as history where no migration has occurred.

## Open implementation questions

Owners, first MATRIX schemas/processes, AIRS reuse/delegation approach, identity/evidence scopes, storage/hosting, costs, review thresholds and rollout/compatibility.
