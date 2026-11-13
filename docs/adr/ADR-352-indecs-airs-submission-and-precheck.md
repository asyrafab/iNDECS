# ADR-352: One iNDECS submission journey with bounded AIRS precheck

Date: 2026-11-04. Status: Accepted.

## Context

AIRS grew from document review into a standalone submission workflow, duplicating iNDECS identity, fees, corrections, assignment and letters. The programme owner confirmed one iNDECS portal, AIRS review processing and AMIRA assistance, with free applicant preparation checks and payment before formal submission.

## Decision

Adopt [the shared integration contract](../integration/indecs-airs-contract.md). iNDECS owns official applications and operational workflows; AIRS owns review artefacts. Applicant precheck is checklist/file validation and bounded embedded-text/local-OCR retrieval, with no paid generative AI or technical compliance assessment. Payment must clear before a chargeable submission is accepted, with only explicitly authorised and audited policy exceptions. Fees and correction-round charging remain undecided. Formal technical review follows accepted submission. Scores are advisory; officers decide in iNDECS. AMIRA assists within record permissions without autonomous actions.

This refines ADR-203 to ADR-206 and ADR-311; it supersedes conflicting standalone AIRS intake/fee/score/letter assumptions. API-only integration and immutable audit/history remain binding. Physical database topology is an explicitly unresolved discrepancy, not silently settled here.

## Consequences

Reuse AIRS operational features in core rather than maintain a second public application registry. Implement verified payment, version-bound/idempotent submission and review requests, resource-isolated precheck, safe result reuse and scoped evidence/feedback APIs. Retain historical prototypes as labelled references. Documentation acceptance does not implement or release these capabilities; the contract lists delivery gates.
