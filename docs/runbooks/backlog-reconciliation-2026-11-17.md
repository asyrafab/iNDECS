# Local backlog reconciliation

Date: 2026-11-17. Review branch: `codex/review-portal-identity-security`.

## History and scope

GitHub main at `5e6ff0e` is an ancestor of the local branch. There are no upstream-only commits and no merge conflicts. The 127 earlier local commits end at `fdf7437`; the four portal/identity/formatting/security follow-ups end at `80476e1`. Preserve this ancestry and submit the complete accumulated change in one draft consolidation PR. No force push, history rewrite, direct main push or merge is needed.

The accumulated change covers:

- Bangunan registration/document workflows and the shared Calm Intelligence workspace.
- eOSC meeting/agenda registration, permission checks, GIS snapshots and meeting-map baseline. NADI remains the in-meeting application direction; this PR does not claim its deployment/integration.
- Public portal, department reference library, deterministic checklist rules and local free document precheck.
- AIRS merged into `app/Airs`, the LJUP two-check applicant journey, audited fee waivers and JKEJ officer findings/field-review workspace.
- Upload-first sorting, bounded local text extraction, applicant-confirmed prefill and multiple files per checklist item.
- Public branding and meeting/reference materials already included in the local backlog.
- Default-off MyDigital ID linking/email OTP sandbox and the six-package dependency security follow-up.

This is a broad consolidation PR. The retained commit history and departmental runbooks provide review boundaries. A green build is not evidence of production readiness or approval to activate external integrations.

## Publication checks and cleanup

Gitleaks 8.30.1 was downloaded from the official release and checked against its published SHA-256 checksum. The initial scan of all 131 unpublished commits found four generic-key matches, all generated `this.textKey_` expressions in `public/vendor/openlayers/ol.js`. The bundle is byte-for-byte identical to the pinned OpenLayers 10.11.0 distribution (SHA-256 `a4e607e98b56846d543e2131161720845c7a80170d5f00aa2bc8b6c4e0f9194a`). A second scan with a temporary path-and-expression-specific exception found no remaining matches. This is an automated secret scan with reviewed false positives, not a comprehensive security audit.

Newly added DOCX/PPTX/ZIP archive inventories contained no credential-like file paths. No `.env`, authentication store or real provider credential file is added. Existing reference documents and approved artwork are retained.

Generated deck build intermediates under `.build-picture-deck`, `tmp/pdfs/architecture-cover.png` and Python bytecode were removed from Git tracking, with local copies retained. Ignore patterns prevent their re-addition. Finished presentation deliverables and required runtime artwork remain tracked.

## Validation and operational limits

The [release review](release-review-2026-11-17.md) records the completed PostgreSQL suite, migration round-trip, formatting, build and dependency audits. All 23 JavaScript tests now run in CI, including the previously omitted CommonJS AMIRA suites. Only publication cleanup, workflow coverage and documentation changed after those application checks.

The real payment gateway and fee, real MyDigital ID/provider/mail interoperability, production activation, and LJUP officer decisions/letters remain open. Real-document acceptance fixtures are not committed and their optional tests remain skipped. No operational database migration or live integration is performed by reconciliation. Merge remains a separate action after hosted CI and review.
