# ADR-413: Engineering officer registration

Date: 2026-11-19

Status: Accepted at the owner's explicit request to enable Daftar permohonan.

Enable staff intake for PKT (Pelan Kerja Tanah) and PKP (Pelan Jalan dan Parit)
under JKEJ using existing typed extension storage, shared reference allocation,
parties/site/lot records and transactional history/audit. A shared core registration
writer handles common record persistence. Engineering accepts common particulars
and optional technical notes only; unconfirmed measurement units, catalogues and
technical assessment stages are not invented.

Introduce scoped Engineering application access/register permissions for existing
engineering officer and senior roles, with unscoped administrator grants. Activate
PKT/PKP and initial registration/submitted workflow templates with no outgoing
technical/approval/payment transitions. A versioned migration runs only the narrow
Engineering registration seeder. No demo accounts or historical applications are
created. Rollback refuses to disable intake while registered records exist.

Engineering lists/dashboard/detail pages include authorised PKT/PKP records and
retain the existing LJUP journey. PKT/PKP use normal structured district scoping;
LJUP keeps its established unlocated-record exception. LJUP cannot be created by
this form: its paid applicant submission and AIRS gates remain unchanged.
Technical review and official outcomes require later confirmed departmental policy.
