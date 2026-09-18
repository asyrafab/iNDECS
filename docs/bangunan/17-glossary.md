# 17. Glossary

## Systems

| Term | Meaning |
|---|---|
| iNDECS | Intergrated Development Control System (Sistem Bersepada Kawalan Pembangunan). MBSP's in-house Laravel platform for development-control applications and the main portal |
| iNDECS Bangunan | The Jabatan Bangunan module of iNDECS. The subject of this programme. Never abbreviated to "PB" |
| iNDECS Perancang, Kejuruteraan, Landskap | The other department modules: planning permission, earthworks and drainage, landscape |
| AIRS | AI Review Studio. In-house Laravel sub-system that reviews submitted plans against versioned rule packs and returns evidence-linked findings |
| AMIRA | AI Multidocument Interpretation and Review Assistant, the AIRS assistant persona |
| LJUP | Pelan Lampu Jalan Untuk Pembangunan, street-lighting plan; the first AIRS module |
| E-Submission, E-Plan Submission System | Esri Malaysia vendor system on ArcGIS Portal where PSPs submit CAD and Excel for ETL into GIS |
| Sistem Bangunan 2.0 | Legacy WiseCore WinForms application on SQL Server `MPSP_Bangunan`, to be retired |
| WiseCore | Vendor of the legacy suite, including Penilaian and Lesen systems |
| eBangunan | Retired working name from version 1 planning. Not used for any system, module or public service; everything is iNDECS Bangunan |
| MBSPpay | MBSP's payment gateway |
| OSC3Plus | National KPKT system for lodging planning and building submissions with local authorities |

## Legal and regulatory

| Term | Meaning |
|---|---|
| Akta 133 | Akta Jalan, Parit dan Bangunan 1974, the building-control statute |
| UKBS 1984 | Undang-Undang Kecil Bangunan Seragam 1984, technical by-laws for buildings |
| Seksyen 19 | Temporary building permits under Akta 133 |
| Seksyen 70(11) | Penalty for commencing work without approval |
| PDPA 2010 | Personal Data Protection Act 2010 |
| Akta Tandatangan Digital 1997 | Digital Signature Act; internal PKI signatures are not licensed signatures under it |
| MS 1184 | Malaysian Standard on accessibility in the built environment |
| Jadual Pelupusan Rekod | Records disposal schedule under Akta Arkib Negara 2003 |

## Organisations and roles

| Term | Meaning |
|---|---|
| MBSP | Majlis Bandaraya Seberang Perai |
| Jabatan Bangunan, Perancang Bandar, Kejuruteraan, Landskap | Building, Town Planning, Engineering, Landscape departments |
| JBANG, JPBD, JKEJ, JLAND | Department codes seeded in iNDECS |
| Penilaian, Pelesenan, Penamaan Jalan dan Penomboran Bangunan, Penguatkuasaan | Valuation, licensing, addressing, enforcement functions |
| KPKT | Ministry of Housing and Local Government |
| LAM, BEM | Board of Architects Malaysia, Board of Engineers Malaysia |
| PSP | Principal Submitting Person (Orang Utama Yang Mengemukakan), the registered professional responsible for a submission. In the Esri URS the same acronym is used for its external users |
| BTM | Bahagian Teknologi Maklumat, MBSP ICT |
| Pengarah, Ketua Bahagian, Pegawai Bangunan, Penolong Pegawai Bangunan, Jurutera, Kerani, Pentadbir Sistem | Director, section head, building officer, assistant building officer, engineer, clerk, system administrator |

## Process and documents

| Term | Meaning |
|---|---|
| Permohonan | Application |
| PKM | Permohonan Kebenaran Merancang, planning permission |
| Pelan bangunan penuh | Full building plan |
| Pelan Tambahan / Ubahsuai Kecil | Small extension or renovation plan |
| Pelan Bangunan Sementara, Permit Sementara | Temporary building plan and permit |
| Meletak bahan binaan, perancah, runtuh, tukar guna | Road-reserve materials, scaffolding, demolition, change of use |
| Kebenaran Merancang (KM), Pelan Saliran (PS), Serentak | Planning permission, drainage plan, simultaneous submission track |
| SKP | Sistem Kelulusan Pelan letter series SKP2 to SKP15 |
| SKP4, SKP5, SKP9, SKP10 | Amendment request, technical circulation, plan approval, permission to commence |
| Ulasan teknikal | Technical comments |
| Jawatankuasa OSC, Mesyuarat YDP | OSC committee, meeting chaired by the council president or mayor |
| Kertas pembentangan, kertas kerja | Working paper tabled at a meeting |
| Lulus, Lulus Bersyarat, Tangguh, Tolak, Ditarik balik | Approved, conditionally approved, deferred, rejected, withdrawn |
| CCC | Certificate of Completion and Compliance |
| Borang B, G1 to G21, F, F1 | Commencement notice, staged completion certifications, completion certificates |
| Notis, Kompaun | Statutory notice, compound fine |
| Wang Cagaran, Pelekat | Deposit, construction safety sticker |
| Fail fizikal, Pergerakan fail | Physical file, file movement |
| Piagam Pelanggan | Client charter, service level commitments |
| Perunding, Pemohon, Tuan tanah | Consultant, applicant, landowner |
| Lot, Mukim, Daerah | Parcel, sub-district, district. Daerah codes SPU, SPT, SPS |
| Geran, Hakmilik | Land title |
| OTTV, SPAH, OKU | Thermal transfer value, rainwater harvesting, accessibility for persons with disabilities |
| Development case | Optional iNDECS grouping of related applications for one development |
| Borang Jabatan | The department-specific form tab in the iNDECS workspace |

## Technical

| Term | Meaning |
|---|---|
| Extension table | Department-specific table keyed to the core application record, the iNDECS module pattern |
| OpenID Connect (OIDC) | Identity protocol iNDECS uses to sign users into AIRS |
| Shadow account, magic link | Passwordless applicant account created on first submission; expiring single-use access link |
| Rule pack | AIRS versioned catalogue of review requirements as data |
| Evidence-linked finding | AIRS finding that cites the document and page supporting it |
| `AiProvider` | The provider-neutral AI interface shared by AIRS and iNDECS |
| PostGIS, `pgvector`, `pg_trgm` | PostgreSQL extensions for geometry, vector search, fuzzy text matching |
| MinIO | Self-hosted S3-compatible object storage |
| Webhook, dead-letter | Signed event delivery; the queue for deliveries that failed all retries |
| ADR | Architecture decision record |
| Conventional Commits | Commit message convention enabling generated changelogs |
