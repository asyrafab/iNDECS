<?php

namespace Tests\Feature\Precheck;

use App\Enums\AmiraMood;
use App\Models\User;
use App\Services\Portal\PortalBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardProgressTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
    }

    public function test_draft_needing_action_moves_to_action_section_with_todo(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->draftDocument($draft, 'geran', ['readable' => false]);
        $board = app(PortalBoard::class)->for($owner);
        $this->assertSame([$draft->id], $board['sections']['action']->pluck('id')->all());
        $this->assertCount(0, $board['sections']['active']);
        $this->assertSame('Menara telekomunikasi: 1 dokumen perlu tindakan', $board['todos'][0]['text']);
        $this->assertSame(route('portal.drafts.edit', $draft).'#dokumen', $board['todos'][0]['href']);
        $this->assertSame(AmiraMood::Alert, $board['mood']);
        $this->assertSame(['ready' => 0, 'total' => 2], $board['progress'][$draft->id]);
    }

    public function test_focus_draft_pending_makes_amira_think(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->draftDocument($draft, 'geran', ['precheck_status' => 'pending', 'readable' => null]);
        $this->assertSame(AmiraMood::Think, app(PortalBoard::class)->for($owner, focus: $draft)['mood']);
    }

    public function test_focus_draft_ready_makes_amira_happy(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->draftDocument($draft, 'geran', ['matched_keywords' => ['HAKMILIK']]);
        $this->draftDocument($draft, 'pelan_lantai');
        $board = app(PortalBoard::class)->for($owner, focus: $draft);
        $this->assertSame(AmiraMood::Happy, $board['mood']);
        $this->assertSame(['ready' => 2, 'total' => 2], $board['progress'][$draft->id]);
    }

    public function test_home_row_shows_document_progress(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->draftDocument($draft, 'pelan_lantai');
        $this->actingAs($owner)->get(route('portal.home'))->assertOk()->assertSee('Dokumen 1/2');
    }

    public function test_keywords_text_round_trip_on_catalogue_page(): void
    {
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
        $this->actingAs($admin)->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertOk()
            ->assertSee('Kata kunci semakan')->assertSee('value="HAKMILIK, NO. LOT"', false);
        $this->post(route('bangunan.document-catalogue.publish', 'TEL'), ['version' => 1, 'source_reference' => 'Ujian 2', 'items' => [
            ['code' => 'geran', 'name' => 'Geran tanah', 'is_required' => '1', 'precheck_keywords_text' => 'hakmilik,  daftar hakmilik , '],
        ]])->assertSessionHasNoErrors();
        $this->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertSee('value="HAKMILIK, DAFTAR HAKMILIK"', false);
    }
}
