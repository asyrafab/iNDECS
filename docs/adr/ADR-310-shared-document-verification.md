# ADR-310 Shared document verification for Bangunan

Date: 2026-10-28. Status: Accepted for implementation.

Continue the operational Bangunan increment inside iNDECS. Document catalogues, checklist snapshots, revision storage and verification are shared core capabilities. Use the existing core document type, requirement and application document tables. Do not create a parallel Bangunan file store or assume that PKM requirements apply to Bangunan.

The repository contains no confirmed Bangunan requirement catalogue. Authorised senior Bangunan officers or system administrators may publish a per-family catalogue with its department source reference. No business requirements or approvals are seeded. Starting verification snapshots that catalogue; subsequent catalogue edits do not alter an active case. Empty/unconfirmed catalogues cannot complete verification.

Keep each uploaded revision in private storage, with its content MIME type, SHA-256, size, scan result and uploader. Scan through an injectable ClamAV command adapter; unavailable scanners, timeouts, detections and errors refuse the upload. Tests inject an explicit scanner double. There is no development bypass. Existing PKM upload behavior is retained for its later Phase 2 migration; the new core service is used by Bangunan first.

Add configured workflow guards for checklist opening, completion, return and resubmission. Require current accepted revisions for required items and review or explicitly waive supplied optional documents. Replacements reset verification. All document mutations check record access, action permission and expected application row version while locking the application; they append audit attribution. State changes remain exclusively in WorkflowService with append-only stage history. Rejected documents and returns need reasons. Fee assessment is entered only after document verification; this increment neither charges nor records a payment.

The workflow configuration is still on the unreleased ADR-309 branch. Extend its first version with guarded document transitions, retaining pinned template IDs and application states. Existing cases already in document verification can initialise a missing checklist explicitly from the current confirmed catalogue, with an audit entry and version check. Do not silently create or replace their checklist at completion.

Deploy only additive migrations and the dedicated configuration seeder locally after tests pass. Do not reseed user accounts or sample applications, invent department checklist content, refresh the consolidated database, or claim Linux staging/production readiness from Windows tests.
