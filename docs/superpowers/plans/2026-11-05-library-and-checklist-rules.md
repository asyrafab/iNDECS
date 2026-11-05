# Department Library and Checklist Rules (Kaunter Maya, step 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Each department gets a versioned reference library that only its
Penolong Pengarah can change, and public documents in it can be downloaded
from the portal's type chooser. The Bangunan document catalogue becomes a set
of conditional checklist rules that only system admins can publish, with an
evaluator that returns the documents a given project needs.

**Architecture:**
- The library is new: `core.library_documents`, append-only
  `core.library_document_versions`, and a type-link table. A
  `LibraryService` enforces the department and role rules, files go on the
  private `local` disk, and staff pages sit under `/library`.
- Checklist rules extend the existing versioned catalogue.
  `document_requirements` gains `conditions` (jsonb) and `guidance` (text).
  `BangunanDocumentCatalogueService::publish` validates them, and a new
  `ChecklistRuleEvaluator` applies them to a project's particulars. The
  option lists for the inputs live in `config/kaunter.php`.

**Tech Stack:** Laravel 12 / PHP 8.3, PostgreSQL (schemas `core`, public),
Blade, `public/css/indecs.css`, PHPUnit feature tests (`composer test`, DB on
port 5434).

**Spec:** `docs/superpowers/specs/2026-11-05-public-portal-kaunter-maya-design.md`
(§8.6 including "Step 2 decisions", and §9 step 2).

## Global Constraints

- Library changes (create, new version, retire) are limited to role `penolong_pengarah`, and only for the user's own `department_id`. System admins cannot change libraries (the owner said only Penolong Pengarah).
- Checklist rules (catalogue publish) are limited to role `system_admin`. Remove the `senior_building_officer` grant of `bangunan.application.documents.configure`.
- Library files are PDF only, at most 51200 KB, stored on the private `local` disk at `library/{department_id}/{document_id}/v{n}.pdf`, and never served from `public/`. Downloads stream with `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.
- Versions are append-only. Reuse `core.prevent_history_mutation()` with a trigger, as `2026_11_04_000200_create_applicant_drafts.php` does.
- Every library mutation writes an `AuditLog` row (`event`: `library_document_created`, `library_version_added`, `library_document_retired`).
- The public portal shows only `visibility = 'public'` and `status = 'active'` documents. Internal documents are never reachable by public accounts. Requesting one returns 404.
- Option lists for the rule inputs come only from `config/kaunter.php` (Task 5). Validation rejects unknown values.
- UI: Bahasa Melayu, tokens only, Lucide icons, existing staff layout `layouts/app.blade.php` and existing classes (`content-card`, `form-card`, `status-badge`, `button-*`).
- The existing Bangunan intake checklist (`BangunanDocumentController::initialise`) keeps its current behaviour. Conditions are used only by the evaluator.
- Existing work is uncommitted on other branches. Work on `feat/kaunter-maya-shell` (step 1 is committed there) and stage only files this plan touches.

## Review Focus

1. **A Penolong Pengarah from another department** must get 403 when changing a library document, and must not be offered the buttons. Test in Task 2.
2. **A non-PDF file disguised with a `.pdf` name**, or an oversized file, must be rejected with a Malay validation message and nothing stored. Test in Task 2.
3. **A retired or internal document** must not appear in the portal chooser, and its public download URL must return 404, even when guessed. Test in Task 4.
4. **A rule with a malformed condition** (unknown work type, negative storey count) must be rejected on publish, and the published version must be unchanged. Test in Task 5.
5. **Particulars at an exact threshold** (storeys equal to `storeys_min`) count as meeting it, and missing particulars never trigger a conditional rule. Test in Task 5.

---

### Task 1: Roles and permissions

**Files:**
- Create: `database/migrations/2026_11_05_000100_library_roles_and_rule_admin.php`
- Modify: `database/seeders/DatabaseSeeder.php` (role list), `database/seeders/BangunanDocumentWorkflowSeeder.php` (configure grant becomes `['system_admin']` only)
- Modify: `tests/Feature/BangunanDocumentVerificationTest.php`, `tests/Feature/WorkspacePresentationTest.php` (catalogue actor becomes `system_admin`)
- Test: `tests/Feature/Library/LibraryPermissionsTest.php`

**Interfaces:**
- Produces:
  - role `penolong_pengarah` (label "Penolong Pengarah", account_type staff);
  - permission `library.manage` (label "Urus perpustakaan jabatan") granted to `penolong_pengarah` with `department_id = null`;
  - permission `library.view` (label "Lihat perpustakaan jabatan") granted to every staff role with `department_id = null`;
  - `User::canManageLibrary(string $departmentId): bool`, true only when `CorePermissionService::userCan($this, 'library.manage')` and `$this->department_id === $departmentId`;
  - `User::canViewLibrary(): bool`.
- The migration inserts the role and permissions idempotently, and deletes the `senior_building_officer` / `bangunan.application.documents.configure` rows. `down()` restores that grant for JBANG.

- [ ] **Step 1: Write the failing tests**

```php
public function test_penolong_pengarah_manages_only_own_department_library(): void
{
    $pp = User::factory()->create(['department_id' => 'JBANG', 'role' => 'penolong_pengarah']);
    $this->assertTrue($pp->canManageLibrary('JBANG'));
    $this->assertFalse($pp->canManageLibrary('JPBD'));
    $this->assertFalse(User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin'])->canManageLibrary('JBANG'));
    $this->assertFalse(User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer'])->canManageLibrary('JBANG'));
    $this->assertTrue(User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer'])->canViewLibrary());
}

public function test_only_system_admin_can_publish_checklist_rules(): void
{
    $senior = User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']);
    $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
    $this->assertFalse($senior->canPerformBangunan('documents.configure', 'TEL'));
    $this->assertTrue($admin->canPerformBangunan('documents.configure', 'TEL'));
}
```

Use `$this->seed()` in `setUp`, as `BangunanDocumentVerificationTest` does.

- [ ] **Step 2: Run them and confirm they fail**

Run: `php artisan test --filter=LibraryPermissionsTest`
Expected: FAIL ("Call to undefined method ...canManageLibrary" and the senior assertion).

- [ ] **Step 3: Implement the migration, the seeder changes and the two `User` methods. Switch the catalogue actor in the two existing tests from `senior_building_officer` to `system_admin`.** In `BangunanDocumentVerificationTest`, add one assertion that the senior officer now gets 403 on `document-catalogue.publish`.

- [ ] **Step 4: Run and confirm they pass**

Run: `php artisan test --filter="LibraryPermissionsTest|BangunanDocumentVerificationTest|WorkspacePresentationTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_11_05_000100_library_roles_and_rule_admin.php database/seeders app/Models/User.php tests/Feature/Library tests/Feature/BangunanDocumentVerificationTest.php tests/Feature/WorkspacePresentationTest.php
git commit -m "feat(library): Penolong Pengarah role; checklist rules limited to system admin"
```

---

### Task 2: Library schema and service

**Files:**
- Create: `database/migrations/2026_11_05_000200_create_department_library.php`
- Create: `app/Models/LibraryDocument.php`, `app/Models/LibraryDocumentVersion.php`
- Create: `app/Services/Library/LibraryService.php`
- Test: `tests/Feature/Library/LibraryServiceTest.php`

**Interfaces:**
- Schema:
  - `core.library_documents`: `id` ulid pk, `department_id` fk departments, `title` string(255), `category` string(30), `description` text nullable, `visibility` string(10) (`public|internal`), `status` string(10) default `active` (`active|retired`), `current_version` unsigned int, `created_by` fk users, `retired_by` nullable fk users, `retired_at` nullable, timestamps. Index `(department_id, status)`.
  - `core.library_document_versions`: `id` bigint pk, `document_id` ulid fk, `version` unsigned int, `original_name` string(255), `path` string(500), `size_bytes` unsigned bigint, `sha256` char(64), `notes` text nullable, `uploaded_by` fk users, `created_at`. Unique `(document_id, version)`. Append-only trigger.
  - `core.library_document_application_types`: `document_id`, `application_type_id`, primary key on both.
- Categories (const `LibraryDocument::CATEGORIES`): `garis_panduan` "Garis panduan", `piawaian` "Piawaian", `undang_undang` "Undang-undang & peraturan", `senarai_semak` "Senarai semak", `contoh` "Contoh / sampel", `lain` "Lain-lain".
- `LibraryService` (all methods run in a transaction, write an AuditLog row, and `abort_unless($actor->canManageLibrary($departmentId), 403)`):
  - `create(User $actor, array $data, UploadedFile $file): LibraryDocument`. `$data` keys: `title`, `category`, `description?`, `visibility`, `application_type_ids?: list<string>`. The department is always `$actor->department_id`.
  - `addVersion(User $actor, LibraryDocument $document, UploadedFile $file, ?string $notes): LibraryDocumentVersion`. Rejects retired documents with a ValidationException on `file`.
  - `retire(User $actor, LibraryDocument $document): void`.
  - `rules(): array`, which returns the validation rules, with file rules `['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:51200']`.
- `LibraryDocument` scopes: `publicFor(string $applicationTypeId)` returns active, public documents linked to the type. `forReference(string $departmentId, ?string $applicationTypeId = null)` returns active documents (both visibilities) for AIRS and AMIRA in later steps. Relations: `versions()`, `currentVersion()`, `applicationTypes()`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_penolong_pengarah_creates_versions_and_retires_with_audit(): void
{
    Storage::fake('local');
    $doc = app(LibraryService::class)->create($this->pp, $this->meta(['application_type_ids' => ['TEL']]), UploadedFile::fake()->create('garis.pdf', 120, 'application/pdf'));
    $this->assertSame('JBANG', $doc->department_id);
    $this->assertSame(1, $doc->current_version);
    Storage::disk('local')->assertExists("library/JBANG/{$doc->id}/v1.pdf");
    $v2 = app(LibraryService::class)->addVersion($this->pp, $doc, UploadedFile::fake()->create('garis-2026.pdf', 80, 'application/pdf'), 'Pindaan 2026');
    $this->assertSame(2, $v2->version);
    $this->assertSame(2, $doc->fresh()->current_version);
    app(LibraryService::class)->retire($this->pp, $doc);
    $this->assertSame('retired', $doc->fresh()->status);
    $this->assertSame(3, AuditLog::query()->where('auditable_id', $doc->id)->count());
}

public function test_other_department_and_non_pp_are_forbidden(): void
{
    $this->expectException(HttpException::class);
    app(LibraryService::class)->create($this->ppOfJpbd, $this->meta(), $this->pdf());
}

public function test_non_pdf_and_oversized_files_are_rejected_and_not_stored(): void
{
    Storage::fake('local');
    foreach ([UploadedFile::fake()->create('nota.pdf', 10, 'text/plain'), UploadedFile::fake()->create('besar.pdf', 51201, 'application/pdf')] as $file) {
        try { app(LibraryService::class)->create($this->pp, $this->meta(), $file); $this->fail('must reject'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('file', $e->errors()); }
    }
    $this->assertSame([], Storage::disk('local')->allFiles('library'));
    $this->assertDatabaseCount('core.library_documents', 0);
}

public function test_versions_are_append_only(): void
{
    // after create: an UPDATE on core.library_document_versions throws QueryException with SQLSTATE 42501
}

public function test_retired_document_cannot_get_new_version(): void { /* ValidationException on 'file' */ }
```

- [ ] **Step 2: Run them and confirm they fail.** Run: `php artisan test --filter=LibraryServiceTest`. Expected: FAIL (class not found).
- [ ] **Step 3: Implement the migration, models and service.** Write the file to disk before the DB commit. If the transaction fails, delete the file.
- [ ] **Step 4: Run and confirm they pass.** Run: `php artisan test --filter=LibraryServiceTest`. Expected: PASS
- [ ] **Step 5: Commit** (`feat(library): versioned department library with audit`)

---

### Task 3: Staff library pages

**Files:**
- Create: `app/Http/Controllers/LibraryController.php`
- Create: `resources/views/library/index.blade.php`, `resources/views/library/show.blade.php`, `resources/views/library/create.blade.php`
- Modify: `routes/web.php` (group `Route::middleware('auth')->prefix('library')->name('library.')`)
- Modify: `resources/views/layouts/app.blade.php` (nav link "Perpustakaan jabatan", icon `library` if it exists in `public/img/lucide.svg`, otherwise `files`; shown when `canViewLibrary()`)
- Modify: `public/css/indecs.css` (only if existing classes are not enough)
- Test: `tests/Feature/Library/LibraryPagesTest.php`

**Interfaces:**
- Consumes: `LibraryService`, `LibraryDocument` (Task 2), `User::canViewLibrary/canManageLibrary` (Task 1).
- Routes:
  - `library.index` GET `/library?department=` (default: the user's department; lists active documents first, retired documents muted with the badge "Dibatalkan")
  - `library.create` GET and `library.store` POST
  - `library.show` GET `/library/{document}`
  - `library.versions.store` POST `/library/{document}/versions`
  - `library.retire` POST `/library/{document}/retire`
  - `library.download` GET `/library/{document}/versions/{version}`
  - Every route aborts 403 unless `canViewLibrary()`. Mutating routes go through `LibraryService`.
- The create, upload-version and retire controls render only when `canManageLibrary($document->department_id)`. Retire uses the existing `data-confirm` pattern.

- [ ] **Step 1: Write the failing tests:**
  - `test_staff_can_browse_and_download_but_only_pp_sees_manage_controls`: a building_officer sees the document and gets 200 with a `attachment` disposition on download, but does not see "Muat naik versi baharu". A PP of JBANG does see it.
  - `test_public_accounts_cannot_open_staff_library`: 403.
  - `test_pp_of_other_department_gets_403_on_store_version_and_retire`.
  - `test_upload_validation_errors_show_in_malay`: posting a text file shows the error message with attribute "fail dokumen".
- [ ] **Step 2: Run them and confirm they fail.** Run: `php artisan test --filter=LibraryPagesTest`. Expected: FAIL (route not defined).
- [ ] **Step 3: Implement the controller, views, routes and nav link.**
- [ ] **Step 4: Run and confirm they pass, then check in the browser** as a PP and as an officer (create, new version, retire, download).
- [ ] **Step 5: Commit** (`feat(library): staff library pages`)

---

### Task 4: Public downloads in the portal chooser

**Files:**
- Modify: `resources/views/public/home.blade.php` (the `portal-download-*` dialogs)
- Modify: `app/Http/Controllers/PublicPortalController.php` (`home` passes `$libraryByType: array<string, Collection<LibraryDocument>>`; new `download(Request, string $document)` action)
- Modify: `routes/public.php` (`portal.library.download` GET `/portal/library/{document}` inside `public.access`)
- Modify: `tests/Feature/PublicPortalTest.php`

**Interfaces:**
- Consumes: `LibraryDocument::publicFor($typeId)` (Task 2).
- Each type's download dialog lists its public documents as links (title, category label, "PDF · v{n}"). When there are none, it keeps the existing disabled Senarai semak / Garis panduan rows labelled "Belum tersedia".
- `download` serves the **current** version only, and only for active public documents. Anything else returns 404.

- [ ] **Step 1: Write the failing tests:**
  - `test_public_library_documents_appear_for_their_type_and_download`;
  - `test_internal_or_retired_documents_are_hidden_and_404`, which also guesses the URL of an internal document's id;
  - `test_type_without_documents_keeps_belum_tersedia`.
- [ ] **Step 2: Run and confirm they fail.** Run: `php artisan test --filter=PublicPortalTest`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run and confirm they pass.** Run: `php artisan test --filter=PublicPortalTest`.
- [ ] **Step 5: Commit** (`feat(portal): download public department library documents`)

---

### Task 5: Conditional checklist rules and evaluator

**Files:**
- Create: `config/kaunter.php`
- Create: `database/migrations/2026_11_05_000300_add_conditions_to_document_requirements.php` (`conditions` jsonb nullable, `guidance` text nullable)
- Modify: `app/Models/DocumentRequirement.php` (cast `conditions` to array)
- Modify: `app/Services/BangunanDocumentCatalogueService.php` (accept and validate `items.*.guidance` and `items.*.conditions`, and store them; the audit `after_values` includes them)
- Create: `app/Services/Checklist/ChecklistRuleEvaluator.php`
- Test: `tests/Feature/Checklist/ChecklistRulesTest.php`

**Interfaces:**
- `config/kaunter.php`, all marked in a comment as "my call, to be confirmed by Jabatan Bangunan":

```php
'work_types' => ['baharu' => 'Bangunan baharu', 'tambahan' => 'Tambahan / pembesaran', 'ubahsuai' => 'Ubah suai', 'roboh' => 'Perobohan', 'pagar' => 'Pagar / tembok'],
'building_categories' => ['teres' => 'Kediaman teres', 'berkembar' => 'Kediaman berkembar', 'sesebuah' => 'Kediaman sesebuah', 'komersial' => 'Komersial', 'industri' => 'Perindustrian', 'institusi' => 'Institusi', 'lain' => 'Lain-lain'],
'site_conditions' => ['lereng_bukit' => 'Lereng bukit', 'rizab_sungai' => 'Berhampiran rizab sungai', 'warisan' => 'Zon warisan', 'strata' => 'Hakmilik strata'],
```

- Condition shape, where every key is optional and an empty or null value means "always required":
  `{"work_types": ["tambahan"], "building_categories": ["teres","berkembar"], "storeys_min": 3, "floor_area_min": 500, "site_conditions": ["lereng_bukit"]}`.
  Validation: list values must be keys from config; `storeys_min` must be an integer from 1 to 100; `floor_area_min` must be numeric, more than 0 and at most 1000000.
- Particulars shape, as passed to the evaluator: `['work_type' => ?string, 'building_category' => ?string, 'storeys' => ?int, 'floor_area' => ?float, 'site_conditions' => list<string>]`.
- Matching: each given key must match. Lists match when the particular is in the list (for `site_conditions`, any overlap). Thresholds match when the particular is at or above the threshold. A missing particular never matches a key that is set.
- `ChecklistRuleEvaluator::documentsFor(string $applicationTypeId, array $particulars): Collection<array{code: string, name: string, guidance: ?string, is_required: bool, conditional: bool}>` returns the active catalogue items in `sort_order`.

- [ ] **Step 1: Write the failing tests:**
  - `test_unconditional_items_always_apply`;
  - `test_lists_and_site_condition_overlap_match`;
  - `test_threshold_is_inclusive_and_missing_particular_never_matches` (storeys 3 with `storeys_min` 3 matches; storeys null with `storeys_min` 3 does not);
  - `test_publish_rejects_unknown_values_and_bad_thresholds_and_keeps_version` (an unknown work type, `storeys_min` -1 and `floor_area_min` 0 each give a validation error on `items.0.conditions…`, and the catalogue version is unchanged);
  - `test_publish_stores_guidance_and_conditions_and_audits_them`.
  Publish as a `system_admin` and seed with `$this->seed()`.
- [ ] **Step 2: Run and confirm they fail.** Run: `php artisan test --filter=ChecklistRulesTest`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="ChecklistRulesTest|BangunanDocumentVerificationTest"`. Expected: PASS.
- [ ] **Step 5: Commit** (`feat(checklist): conditional catalogue rules and evaluator`)

---

### Task 6: Rule editing on the catalogue page

**Files:**
- Modify: `resources/views/bangunan/document-catalogue.blade.php` (per item: a "Keperluan dokumen" textarea `items[i][guidance]`; a collapsible "Syarat (pilihan)" `<details>` with checkbox groups for work types, building categories and site conditions, and number inputs for `storeys_min` and `floor_area_min`; the new-row template matches)
- Modify: `app/Http/Controllers/BangunanDocumentCatalogueController.php` (`edit` maps `guidance` and `conditions` into `$items`; `publish` normalises empty strings and empty arrays to null before calling the service)
- Modify: `public/css/indecs.css` (condition group layout, tokens only)
- Test: `tests/Feature/Checklist/ChecklistRulesPageTest.php`

**Interfaces:**
- Consumes: the Task 5 validation and `config('kaunter')`.
- Form field names: `items[i][conditions][work_types][]`, `items[i][conditions][building_categories][]`, `items[i][conditions][site_conditions][]`, `items[i][conditions][storeys_min]`, `items[i][conditions][floor_area_min]`.
- Each item's summary line reads its conditions in Malay, for example "Wajib jika: Tambahan · 3 tingkat ke atas", or "Sentiasa diperlukan" when it has none.

- [ ] **Step 1: Write the failing tests:**
  - `test_admin_sees_condition_editor_with_config_options`;
  - `test_round_trip_publish_then_edit_shows_saved_conditions`;
  - `test_senior_officer_gets_403_on_page`.
- [ ] **Step 2: Run and confirm they fail.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run and confirm they pass, then check in the browser** as `system_admin`: add a conditional item, publish it, and reload.
- [ ] **Step 5: Commit** (`feat(checklist): edit rule conditions on the catalogue page`)

---

### Task 7: Decision record and documentation

**Files:**
- Create: `docs/adr/ADR-362-department-library-and-checklist-rules.md`. Cover:
  - the library model and roles;
  - PDF only;
  - public versus internal documents;
  - catalogue rules restricted to system admin (superseding the earlier senior-officer grant);
  - the condition model;
  - how the evaluator is consumed by step 3;
  - `forReference` for AIRS and AMIRA later.
- Modify: `docs/adr/README.md`, `AGENTS.md` (new section "Department library and checklist rules"), `docs/runbooks/public-portal.md` (downloads), and a new `docs/runbooks/department-library.md` (how to assign the `penolong_pengarah` role, upload, retire, and where files live).

- [ ] **Step 1: Write the documents.**
- [ ] **Step 2: Run** `composer test`. Expected: all green.
- [ ] **Step 3: Commit** (`docs: ADR-362 department library and checklist rules`)
