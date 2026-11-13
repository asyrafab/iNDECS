# Independent AMIRA backend

Date: 2026-11-04. Decision: [ADR-353](../adr/ADR-353-independent-amira-backend.md).

AMIRA's backend source lives at `C:\Users\Asyra\Documents\AMIRA`, independently of AIRS and iNDECS. The current implementation is a stateless PHP service for prepared Bahasa Melayu guidance/catalogues, with per-system server-side authentication. It does not implement conversation, model inference, document access, user delegation or business actions, and requires no database.

## Ownership and consumers

- iNDECS owns identity, applications, payment, official workflow and decisions.
- AIRS owns document processing, rule evaluation, findings, evidence and its review AiProvider.
- AMIRA owns assistant backend capabilities and the extracted processing-message/avatar-state catalogue. Future conversation and authorised source retrieval belong here.
- Panels, avatar assets and rendering remain in their host applications. Backend ownership does not require moving the UI.

AIRS's assessment page uses a published local catalogue and an explicit authenticated `amira:sync-catalogue` command. Normal page loads do not contact AMIRA or a model. iNDECS's existing local page guidance is unchanged and has no new remote dependency; its live client remains future work. The shared [integration contract](indecs-airs-contract.md) continues to require free bounded precheck and cleared payment before accepted formal submission/AIRS technical review.

## Local operation

Read the AMIRA project's README. Its development launcher serves loopback port 8770. Only liveness at `/health` is public. `/api/v1/catalogue`, `/api/v1/capabilities` and `/api/v1/guidance` require a distinct server-side token per client. Never render tokens into browser code or copy existing AIRS/iNDECS credentials into the new project. No client tokens or live connections are configured automatically.

The initial service accepts only fixed processing-status requests and returns prepared text. A caller-provided status is not independently verified; the host must derive it from an authorised record. No record context or arbitrary questions are accepted. Future record access requires iNDECS user delegation and permission checks at the owning iNDECS/AIRS API; the static-catalogue service token is insufficient authority.

## Remaining work

Production service provisioning, TLS, gateway quotas, monitoring/rotation, iNDECS client implementation, scoped source adapters and conversational privacy/audit/budget rules remain pending. Do not change availability labels to imply a working chatbot. All systems must remain usable during AMIRA outages; applicant preparation guidance stays local with no paid AI calls.
