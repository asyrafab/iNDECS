# ADR-353: AMIRA backend lives outside AIRS and iNDECS

Date: 2026-11-04. Status: Accepted. Refines ADR-352 and ADR-311.

## Context

The programme owner directs AMIRA's backend to `C:\Users\Asyra\Documents\AMIRA`. Inspection of AIRS finds scripted avatar/guidance in Blade, not an implemented conversational backend. Its `AiProvider`, document workers and rule services are the AIRS review engine and must remain there.

## Decision

Establish an independently runnable PHP AMIRA backend in the named directory. Extract the reusable Bahasa Melayu processing messages, avatar labels and workflow-to-avatar mapping from AIRS into an AMIRA-owned, versioned catalogue. AIRS consumes a published local catalogue with an explicit server-side sync command. Its UI/assets remain in AIRS. Public preparation guidance does not require a live service or paid AI.

The initial service exposes authenticated, read-only catalogue and deterministic guidance endpoints; it does not claim free-text conversation, model inference, document access or official decision capabilities. Use distinct server-side tokens per consuming system; never put these tokens in browser code. The service requires no database for this stateless capability. It neither reads application databases nor migrates/copies applicant records or credentials.

iNDECS keeps its existing local guidance and does not receive a new runtime dependency in this increment. Future conversation and evidence retrieval belong to AMIRA, using scoped iNDECS identity and authorised iNDECS/AIRS service contracts. They require a separately implemented delegation, audit and usage-budget design before activation. The AIRS review AI gateway remains in AIRS.

## Consequences

Record the source inventory and remaining conversation/integration work in AMIRA's own documentation. Keep deployment independent, fail closed without configured client tokens, validate request/catalogue shape and preserve local fallback during service outages. Existing AIRS document review, state transitions and visual assets are unchanged. The shared integration contracts and agent instructions must describe AMIRA as a third backend, rather than an AIRS-owned assistant API.
