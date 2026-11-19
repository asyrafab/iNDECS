# Engineering officer registration

ADR-413 enables **Daftar permohonan** for PKT (Pelan Kerja Tanah) and PKP (Pelan
Jalan dan Parit) at `/kejuruteraan/create`, posting to `kejuruteraan.store`.
The Engineering list, dashboard and task subject selector include authorised
native records. Existing `kejuruteraan.show` displays their registration details,
parties, sites/lots and history. LJUP retains its established review workspace.

Apply `2026_11_19_010000_enable_engineering_officer_registration.php`; it invokes
only `EngineeringRegistrationSeeder`. Existing PKT/PKP typed storage is required.
The seeder activates the two types and initial registration/submitted workflow
templates, defines `kejuruteraan.application.access/register`, and grants scoped
access/register to the existing engineering officer/senior roles and unscoped
administrator rights. New-type task grants follow the approved policy: ordinary
officers view/work and senior officers assign. No accounts, historical records,
LJUP rights, charges, document catalogues or technical transitions are created.
Rerunning the seeder preserves existing rows and adds no duplicate grants.

Registration validates the type, active service, current permission, district,
applicant, site and at least one distinct lot. It snapshots party particulars,
allocates the shared type/year reference, writes the correct departmental
extension, and appends stage history/audit in one transaction. Optional technical
notes use the existing extension field. Numeric technical units and assessment
requirements remain for departmental confirmation. No arbitrary workflow/status,
assignee or fee data is accepted from the form.

**LJUP is not offered in this form.** Its applicant payment/waiver, paid-check,
amendment and submission gates are unchanged. Native access alone cannot open
LJUP records or review operations. PKT/PKP use normal structured district scope;
LJUP retains its existing unlocated-record visibility exception.

Before local activation, create and verify a full PostgreSQL backup and record
its SHA-256. In a shared checkout with other pending migrations, run only this
specific migration path; do not run unrelated migrations or the full demo seeder.
Record an owner-authorised activation audit with the exact permission/grant delta.

Verify both types end to end, type/district/role denial, removal of registration
rights, active-service/workflow checks, and no partial record/reference/history
after a failed write. Check LJUP access and its paid journey independently. A
clean migration rollback/re-apply must succeed before records exist. Rollback
refuses to disable the workflow while PKT/PKP records exist; retain the records
and restore service configuration through an authorised forward change instead.

## Local activation and validation — 19 November 2026

Only the named Engineering migration was applied to the local `iNDECS` database
after a full custom-format PostgreSQL backup, archive-list verification and SHA-256
check. It added two permission definitions and 20 Engineering/admin/task grants;
existing roles, permission rows and unrelated workflows were preserved. Activation
audit ID: 76. No normal accounts or applications were created. A read-only form
render confirmed PKT/PKP options and enabled staff navigation. Private receipts
and backups remain in ignored `output/engineering-activation-2026-11-19/`.

Clean isolated validation: 944 PHP tests passed, two optional fixtures skipped,
5,002 assertions; 40 JavaScript tests, full Pint and build passed. Rollback/re-apply,
record-protection guard and full browser registration for both types were checked.
Hosted CI and production acceptance are separate; this records local activation.
