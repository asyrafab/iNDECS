# Department library and checklist rules

ADR-362. Staff page: **Perpustakaan jabatan** (`/library`) in the staff sidebar.

## Who can do what

| Action | Who |
|---|---|
| Browse and download any department's library | Every active staff role (`library.view`) |
| Add a document, upload a new version, retire | Penolong Pengarah (role `penolong_pengarah`) of **that** department only |
| Set checklist rules (Bangunan document catalogue) | System admin only |

To make someone a Penolong Pengarah, set their `users.role` to
`penolong_pengarah` and their `department_id` to their department. Do this
through the usual staff administration process. A Penolong Pengarah also gets
their department's senior-officer workspace access, but cannot publish
checklist rules.

## Library documents

- Files must be PDF, up to 50 MB.
- Files are stored privately at
  `storage/app/private/library/{department}/{document}/v{n}.pdf`.
- Each upload is a new version. Old versions stay downloadable and can never
  be edited or deleted, because the database trigger rejects it.
- **Awam** documents linked to an application type appear in the Portal Awam
  type chooser (download icon). **Dalaman** documents are for staff, AIRS and
  AMIRA only.
- Retiring a document is final. It disappears from the portal and from
  AIRS/AMIRA reference, and stays visible to staff marked "Dibatalkan".
- Every change is written to `audit_logs`. The events are
  `library_document_created`, `library_version_added` and
  `library_document_retired`.

## Checklist rules

Open Bangunan, then *Katalog dokumen jabatan*, then the type. For each
document you can set:

- **Keperluan dokumen**: what the document must contain. This text is shown
  to applicants in later steps.
- **Syarat (pilihan)**: when the document is required. Leave it empty for
  "always".
  - Lists match if the project's value is ticked.
  - For site conditions, any one ticked condition is enough.
  - Storeys and floor area are minimums, inclusive.
  - When several conditions are set, all of them must be met.

The option lists are in `config/kaunter.php`. They are a starting proposal
that Jabatan Bangunan must confirm. You can change labels freely, but never
rename or remove a key that a published rule already uses.

## Verify

```bash
php artisan test --filter="Library|Checklist|PublicPortalTest|BangunanDocumentVerificationTest"
```
