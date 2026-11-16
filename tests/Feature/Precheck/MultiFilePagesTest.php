<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Models\User;
use App\Services\BangunanDocumentCatalogueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The item window lists every file of a multi-file item, each with its own actions. */
final class MultiFilePagesTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
        app(BangunanDocumentCatalogueService::class)->publish('TEL', ['version' => 0, 'source_reference' => 'Ujian', 'items' => [
            ['code' => 'geran', 'name' => 'Geran tanah', 'is_required' => true],
            ['code' => 'pelan_lantai', 'name' => 'Pelan lantai', 'is_required' => true, 'max_files' => 5],
        ]], $admin);
    }

    public function test_multi_item_window_lists_every_file_with_its_own_actions(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $files = collect(['borang.pdf', 'umum.pdf', 'khusus.pdf'])->map(fn ($name) => $this->draftDocument($draft, 'pelan_lantai', ['original_name' => $name]));
        $needs = $this->draftDocument($draft, 'pelan_lantai', ['original_name' => 'kabur.pdf', 'text_chars' => 5]);

        $page = $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk();

        $page->assertSeeInOrder(['borang.pdf', 'umum.pdf', 'khusus.pdf', 'kabur.pdf'])->assertSee('Tambah fail')
            ->assertSee(route('portal.drafts.documents.confirm', [$draft, $needs]), false)
            ->assertDontSee('/5 fail');
        foreach ($files->push($needs) as $file) {
            $page->assertSee('action="'.route('portal.drafts.documents.remove', [$draft, $file]).'"', false);
        }
    }

    public function test_single_item_window_is_unchanged(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $geran = $this->draftDocument($draft, 'geran');

        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Muat naik semula')
            ->assertDontSee('action="'.route('portal.drafts.documents.remove', [$draft, $geran]).'"', false);
    }

    public function test_full_multi_item_offers_no_more_uploads(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        foreach (range(1, 5) as $n) {
            $this->draftDocument($draft, 'pelan_lantai', ['original_name' => "bahagian-{$n}.pdf"]);
        }

        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertDontSee('Tambah fail')->assertSee('Buang satu fail untuk menambah fail lain.');
    }
}
