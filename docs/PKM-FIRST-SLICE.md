# PKM First Slice

## Review outcome

`OVERVIEW.md` describes a sound target architecture: applications are the centre of the model, development cases are optional, application relationships are flexible, and departmental fields extend a shared core. PKM is explicitly identified as the first implementation module in Phase 2.

The most important implementation consequence is that PKM must not become the platform's core table. Shared identity, parties, sites, lots, workflow history and audit information belong to reusable core records; planning-permission fields belong to the PKM extension. The current implementation follows that boundary.

## Delivered scope

| Overview capability | First-slice implementation |
|---|---|
| Application registration | Common `applications` record with stable ULID and yearly PKM reference |
| Applicant and professional parties | Reusable parties plus an immutable submitted snapshot per role |
| Site and cadastral lots | Reusable site and lot records; one application can contain several lots |
| Department-specific PKM data | `pkm_applications` and `pkm_components` extension tables |
| Configurable workflow foundation | Typed stage/status values and one guarded transition into completeness review |
| Status and audit history | Append-only stage history and audit-event records |
| Multiuser protection | `row_version` increments on workflow transitions |
| Optional development case | Nullable core relationship and a development-case table; UI intentionally deferred |
| GIS integration | Stable site identifier and ArcGIS GlobalID field; feature service integration deferred |
| Authentication and access | Session login plus active-user, JPBD department and PKM-role enforcement |
| Document requirements | Configurable catalogue with common and PKM-category-specific requirements |
| Document management | Private versioned uploads, checksums, current-version tracking and controlled downloads |
| Completeness review | Per-item officer verification, complete/return outcomes and immutable resubmission cycles |
| Assignment and SLA | Officer assignment with a five-working-day completeness due date |

## Data boundary

```text
Application (shared core)
├── PKM details
│   └── Development components
├── Application party snapshots
│   └── Reusable party records
├── Sites
├── Cadastral lots
├── Stage history
└── Audit log
```

Future PB, PKT, PKP and PL modules should attach to `applications` in the same way as PKM. They should not add their fields to the core application table.

## Deliberately deferred

The operational intake slice now stops after a complete review enters fee assessment. It does not simulate downstream stages that require real domain records.

- MBSP single sign-on and production identity-provider integration
- Document malware scanning, production object storage and retention automation
- Fee assessment and payment verification
- Technical circulation and departmental comments
- Committee meetings, decisions, approval letters and conditions
- Officer assignment, SLA monitoring and notifications
- ArcGIS feature-service editing and spatial validation
- Excel staging and migration
- AI services

This avoids a misleading workflow where a button changes status without the fee, meeting, review or approval evidence required by the overview.

## Discovery decisions still required

1. Confirm the authoritative PKM workflow variants and the conditions that skip technical circulation or committee meetings.
2. Confirm controlled codes for PKM category, permission type, land use, development component and planning standards.
3. Confirm the PKM reference-number format and whether OSC references must be unique.
4. Identify mandatory documents by PKM subtype and amendment cycle.
5. Confirm fee rules, payment source system and receipt-verification authority.
6. Confirm MBSP identity provider, department hierarchy, roles and delegation rules.
7. Identify ArcGIS feature services, coordinate system, cadastral source and GlobalID strategy.
8. Map current PKM Excel columns, duplicates and historical status values.
9. Define SLA targets and escalation owners for each PKM stage.
10. Confirm retention, privacy classification and attachment-storage policies.

## Delivered second slice

**PKM Completeness and Documents** now provides:

1. Local authentication with department and PKM-role authorization.
2. Required-document checklists configurable by PKM category and type.
3. Private versioned uploads and officer verification.
4. Completeness outcomes, missing-item reasons and return-for-amendment cycles.
5. Planning-officer assignment with due dates.

## Recommended next slice

Build **PKM Fees and Payment Verification** next. It should add fee rules, assessments, invoices, transactions, receipt verification and outstanding-balance calculation before allowing technical circulation.
