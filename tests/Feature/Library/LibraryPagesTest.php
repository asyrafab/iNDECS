<?php

namespace Tests\Feature\Library;

use App\Models\LibraryDocument;
use App\Models\User;
use App\Services\Library\LibraryService;
use App\Services\PublicIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryPagesTest extends TestCase
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

    public function test_staff_can_browse_and_download_but_only_pp_sees_manage_controls(): void
    {
        $doc = $this->document();
        $officer = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);

        $this->actingAs($officer)->get(route('library.index'))->assertOk()
            ->assertSee('Perpustakaan jabatan')->assertSee('Garis panduan pelan')->assertDontSee('Tambah dokumen');
        $this->get(route('library.show', $doc))->assertOk()->assertSee('Versi 1')->assertDontSee('Muat naik versi baharu')->assertDontSee('Batalkan dokumen');
        $response = $this->get(route('library.download', [$doc, 1]))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $this->actingAs($this->pp)->get(route('library.index'))->assertOk()->assertSee('Tambah dokumen');
        $this->get(route('library.show', $doc))->assertOk()->assertSee('Muat naik versi baharu')->assertSee('Batalkan dokumen');
    }

    public function test_pp_creates_adds_version_and_retires_through_pages(): void
    {
        $this->actingAs($this->pp)->get(route('library.create'))->assertOk();
        $this->post(route('library.store'), ['title' => 'Piawaian lif', 'category' => 'piawaian', 'visibility' => 'internal',
            'application_type_ids' => ['TEL'], 'file' => $this->pdf()])->assertRedirect();
        $doc = LibraryDocument::query()->where('title', 'Piawaian lif')->firstOrFail();
        $this->post(route('library.versions.store', $doc), ['file' => $this->pdf(), 'notes' => 'Kemas kini'])->assertRedirect(route('library.show', $doc));
        $this->assertSame(2, $doc->fresh()->current_version);
        $this->post(route('library.retire', $doc))->assertRedirect(route('library.show', $doc));
        $this->assertSame('retired', $doc->fresh()->status);
        $this->get(route('library.show', $doc))->assertSee('Dibatalkan')->assertDontSee('Muat naik versi baharu');
    }

    public function test_public_accounts_cannot_open_staff_library(): void
    {
        $doc = $this->document();
        $public = app(PublicIdentityService::class)->create('awam@example.test', 'Awam', 'long-test-password');
        $public->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($public);
        foreach ([route('library.index'), route('library.show', $doc), route('library.download', [$doc, 1])] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_pp_of_other_department_gets_403_on_store_version_and_retire(): void
    {
        $doc = $this->document();
        $this->actingAs(User::factory()->create(['department_id' => 'JPBD', 'role' => 'penolong_pengarah']));
        $this->post(route('library.versions.store', $doc), ['file' => $this->pdf()])->assertForbidden();
        $this->post(route('library.retire', $doc))->assertForbidden();
        $this->get(route('library.show', $doc))->assertOk()->assertDontSee('Muat naik versi baharu');
        $this->assertSame(1, $doc->fresh()->current_version);
    }

    public function test_upload_validation_errors_show_in_malay(): void
    {
        $this->actingAs($this->pp)->from(route('library.create'))->post(route('library.store'), [
            'title' => 'Nota', 'category' => 'lain', 'visibility' => 'public', 'file' => UploadedFile::fake()->create('nota.pdf', 5, 'text/plain'),
        ])->assertRedirect(route('library.create'))->assertSessionHasErrors('file');
        $this->assertStringContainsString('fail dokumen', session('errors')->first('file'));
        $this->followingRedirects()->from(route('library.create'))->post(route('library.store'), [
            'title' => 'Nota', 'category' => 'lain', 'visibility' => 'public', 'file' => UploadedFile::fake()->create('nota.pdf', 5, 'text/plain'),
        ])->assertOk()->assertSee('fail dokumen');
    }

    public function test_unknown_version_returns_404(): void
    {
        $doc = $this->document();
        $this->actingAs($this->pp)->get(route('library.download', [$doc, 9]))->assertNotFound();
    }

    private function document(): LibraryDocument
    {
        return app(LibraryService::class)->create($this->pp, ['title' => 'Garis panduan pelan', 'category' => 'garis_panduan',
            'visibility' => 'public', 'application_type_ids' => ['TEL']], $this->pdf());
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('dokumen.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }
}
