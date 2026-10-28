# ADR-309 Bangunan registration and the first shared workflow transition

Date: 2026-10-27. Status: Accepted for implementation under the Bangunan-first delivery instruction.

Build operational Bangunan registration in the existing Laravel application. Reuse core applications, parties, sites, lots, reference sequences, permissions, audit and append-only history. Department fields live only in `bangunan.app_pb_applications`. PB requires an OSC reference; scoped officers cannot register or access another district. Each family has an independent yearly reference sequence.

Introduce versioned `core.workflow_templates` and configured `core.workflow_transitions`; pin each new application to its template. The first released transition starts document verification. Its service locks the application, checks record access, permission, template, source stage/status and the submitted row version, then changes state and appends audit/history atomically. Existing PKM retains its current workflow until module rollout Phase 2. No approval, fee, AIRS or committee completion is implied by this increment.

Only building officers and senior building officers receive department-scoped registration/review grants; system administrators receive explicit global grants. No user account is created or promoted. Type-specific grants and district scoping apply to lists, metrics, forms, detail views and transitions. Future Jabatan Bangunan field adjustments extend the department record; shared workflow behavior stays in core services.

Use the existing Blade shell, CSS classes and design tokens, in Bahasa Melayu. Linux deployment remains the target; PHP code has no host-specific paths or shell dependencies. Test on the isolated `indecs_test` PostgreSQL database. Apply additive migrations to the local development database only after tests pass; never refresh or seed the consolidated development database wholesale.

ADR-211 in the programme decision log sets module rollout 1 to Bangunan and rollout 2 to PKM. A–E remain technical milestones. This increment does not complete Phase A or B, enable production cutover, or migrate legacy lookup records into operational applications.
