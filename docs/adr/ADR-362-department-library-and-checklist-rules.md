<a id="adr-362"></a>
# ADR-362: Department library and conditional checklist rules

Date: 2026-11-05. Status: Accepted by the programme owner.

## Context

The Kaunter Maya design
([spec](../superpowers/specs/2026-11-05-public-portal-kaunter-maya-design.md),
§8.6) needs two things:

- A reference library per department, which AIRS and AMIRA can consult and
  applicants can download from.
- Deterministic required-document rules that depend on the project, because
  the payment gate must not depend on AI.

The owner decided:

- Only a Penolong Pengarah uploads, replaces or retires library documents.
- Only system admin sets checklist rules.
- Rules depend on the type of work, building category, size or storeys, and
  site conditions.

## Decision

- **Roles.**
  - New staff role `penolong_pengarah` with permission `library.manage`.
    `User::canManageLibrary($dept)` requires that role, that permission and the
    same department.
  - System admin also holds `library.manage`, to keep the "superadmin holds
    every permission" invariant. The role check keeps the owner's rule.
  - A Penolong Pengarah inherits their department's senior-officer grants,
    except catalogue publishing, so they can sign in. Holding the library
    permission is also enough to reach the staff workspace.
  - Every staff role holds `library.view`.
  - `bangunan.application.documents.configure` is now granted to
    `system_admin` only. This supersedes the earlier senior-officer grant.
- **Library.**
  - Tables: `core.library_documents`, `core.library_document_versions` and
    `core.library_document_application_types`.
  - Versions are append-only, enforced by the
    `core.prevent_history_mutation()` trigger.
  - Files are PDF only, at most 50 MB, stored on the private `local` disk at
    `library/{dept}/{document}/v{n}.pdf`. Downloads are sent as attachments
    with `nosniff`.
  - Visibility is `public` (offered to applicants for the linked types) or
    `internal` (staff, AIRS and AMIRA only). Retiring a document is final and
    hides it everywhere outside the staff library.
  - `App\Services\Library\LibraryService` is the only writer, and every change
    is audited.
- **Portal.** The ADR-360 download dialog lists a type's public, active
  documents (`LibraryDocument::publicFor`). It falls back to "Belum tersedia"
  when there are none. `portal.library.download` serves only the current
  version of a public, active document; any other request returns 404.
- **Checklist rules.**
  - The existing versioned Bangunan catalogue gains `document_requirements.conditions`
    (jsonb) and `guidance` (text).
  - Condition keys are `work_types`, `building_categories` and
    `site_conditions` (any overlap), plus `storeys_min` and `floor_area_min`
    (inclusive thresholds). The option lists live in `config/kaunter.php` and
    are pending confirmation by Jabatan Bangunan.
  - `App\Services\Checklist\ChecklistRuleEvaluator::documentsFor($type, $particulars)`
    returns the documents that apply. A condition that is set must match, and
    an unknown particular never matches.
  - The staff intake checklist is unchanged.
- **For later steps.** `LibraryDocument::forReference($dept, $type)` gives
  AIRS and AMIRA a department's active documents. Step 3 (upload and
  pre-check) consumes the evaluator.

## Validation

`tests/Feature/Library/*`, `tests/Feature/Checklist/*`, and the updated
`BangunanDocumentVerificationTest`, `WorkspacePresentationTest` and
`PublicPortalTest`.
