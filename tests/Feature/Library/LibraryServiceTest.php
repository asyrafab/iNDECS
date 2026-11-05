<?php

namespace Tests\Feature\Library;

use App\Models\AuditLog;
use App\Models\LibraryDocument;
use App\Models\User;
use App\Services\Library\LibraryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LibraryServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $pp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->pp = User::factory()->create(['department_id' => 'JBANG', 'role' => 'penolong_pengarah']);
    }

    public function test_penolong_pengarah_creates_versions_and_retires_with_audit(): void
    {
        $service = app(LibraryService::class);
        $doc = $service->create($this->pp, $this->meta(['application_type_ids' => ['TEL']]), $this->pdf('garis.pdf'));

        $this->assertSame('JBANG', $doc->department_id);
        $this->assertSame(1, $doc->current_version);
        $this->assertSame(['TEL'], $doc->applicationTypes()->pluck('id')->all());
        Storage::disk('local')->assertExists("library/JBANG/{$doc->id}/v1.pdf");

        $v2 = $service->addVersion($this->pp, $doc, $this->pdf('garis-2026.pdf'), 'Pindaan 2026');
        $this->assertSame(2, $v2->version);
        $this->assertSame('garis-2026.pdf', $v2->original_name);
        $this->assertSame(64, strlen($v2->sha256));
        $this->assertSame(2, $doc->fresh()->current_version);
        Storage::disk('local')->assertExists("library/JBANG/{$doc->id}/v2.pdf");

        $service->retire($this->pp, $doc);
        $this->assertSame('retired', $doc->fresh()->status);
        $this->assertSame(
            ['library_document_created', 'library_version_added', 'library_document_retired'],
            AuditLog::query()->where('auditable_id', $doc->id)->orderBy('id')->pluck('event')->all(),
        );
    }

    public function test_other_department_and_non_pp_are_forbidden(): void
    {
        foreach ([
            User::factory()->create(['department_id' => 'JPBD', 'role' => 'penolong_pengarah']),
            User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']),
            User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']),
        ] as $actor) {
            try {
                $doc = app(LibraryService::class)->create($actor, $this->meta(), $this->pdf());
                // A JPBD PP may create in JPBD, but never in JBANG; the department always comes from the actor.
                $this->assertSame('JPBD', $doc->department_id);
                $this->assertSame('penolong_pengarah', $actor->role);
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $jbangDoc = app(LibraryService::class)->create($this->pp, $this->meta(), $this->pdf());
        $jpbdPp = User::factory()->create(['department_id' => 'JPBD', 'role' => 'penolong_pengarah']);
        foreach ([fn () => app(LibraryService::class)->addVersion($jpbdPp, $jbangDoc, $this->pdf(), null), fn () => app(LibraryService::class)->retire($jpbdPp, $jbangDoc)] as $call) {
            try {
                $call();
                $this->fail('Another department must not change this library.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(1, $jbangDoc->fresh()->current_version);
        $this->assertSame('active', $jbangDoc->fresh()->status);
    }

    public function test_non_pdf_and_oversized_files_are_rejected_and_not_stored(): void
    {
        foreach ([UploadedFile::fake()->create('nota.pdf', 10, 'text/plain'), UploadedFile::fake()->create('besar.pdf', 51201, 'application/pdf')] as $file) {
            try {
                app(LibraryService::class)->create($this->pp, $this->meta(), $file);
                $this->fail('File must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }
        $this->assertSame([], Storage::disk('local')->allFiles('library'));
        $this->assertDatabaseCount('core.library_documents', 0);
    }

    public function test_file_errors_are_in_malay(): void
    {
        foreach ([
            [UploadedFile::fake()->create('nota.pdf', 10, 'text/plain'), 'fail dokumen mesti berjenis fail: pdf.'],
            [UploadedFile::fake()->create('besar.pdf', 51201, 'application/pdf'), 'fail dokumen tidak boleh melebihi 51200 kilobait.'],
        ] as [$file, $expected]) {
            try {
                app(LibraryService::class)->create($this->pp, $this->meta(), $file);
                $this->fail('File must be rejected.');
            } catch (ValidationException $e) {
                $this->assertContains($expected, $e->errors()['file']);
            }
        }
    }

    public function test_metadata_is_validated(): void
    {
        try {
            app(LibraryService::class)->create($this->pp, $this->meta(['category' => 'rahsia', 'visibility' => 'semua', 'application_type_ids' => ['NOPE']]), $this->pdf());
            $this->fail('Metadata must be validated.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category', $e->errors());
            $this->assertArrayHasKey('visibility', $e->errors());
            $this->assertArrayHasKey('application_type_ids.0', $e->errors());
        }
    }

    public function test_versions_are_append_only(): void
    {
        app(LibraryService::class)->create($this->pp, $this->meta(), $this->pdf());
        try {
            DB::transaction(fn () => DB::table('core.library_document_versions')->update(['notes' => 'ubah']));
            $this->fail('Version rows must be immutable.');
        } catch (QueryException $e) {
            $this->assertSame('42501', $e->errorInfo[0]);
        }
    }

    public function test_retired_document_cannot_get_new_version(): void
    {
        $doc = app(LibraryService::class)->create($this->pp, $this->meta(), $this->pdf());
        app(LibraryService::class)->retire($this->pp, $doc);
        $this->expectException(ValidationException::class);
        app(LibraryService::class)->addVersion($this->pp, $doc->fresh(), $this->pdf(), null);
    }

    public function test_scopes_for_portal_and_reference(): void
    {
        $service = app(LibraryService::class);
        $public = $service->create($this->pp, $this->meta(['title' => 'Awam', 'application_type_ids' => ['TEL']]), $this->pdf());
        $service->create($this->pp, $this->meta(['title' => 'Dalaman', 'visibility' => 'internal', 'application_type_ids' => ['TEL']]), $this->pdf());
        $retired = $service->create($this->pp, $this->meta(['title' => 'Lama', 'application_type_ids' => ['TEL']]), $this->pdf());
        $service->retire($this->pp, $retired);

        $this->assertSame(['Awam'], LibraryDocument::query()->publicFor('TEL')->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['Awam', 'Dalaman'], LibraryDocument::query()->forReference('JBANG', 'TEL')->pluck('title')->all());
        $this->assertSame([], LibraryDocument::query()->publicFor('PB')->pluck('title')->all());
        $this->assertSame($public->id, LibraryDocument::query()->publicFor('TEL')->value('id'));
    }

    private function meta(array $overrides = []): array
    {
        return array_replace(['title' => 'Garis panduan pelan bangunan', 'category' => 'garis_panduan', 'description' => null, 'visibility' => 'public', 'application_type_ids' => []], $overrides);
    }

    private function pdf(string $name = 'dokumen.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }
}
