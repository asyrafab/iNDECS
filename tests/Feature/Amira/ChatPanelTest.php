<?php

namespace Tests\Feature\Amira;

use App\Models\AmiraConversation;
use App\Models\ApplicantDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

class ChatPanelTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    private User $user;

    /** These tests act as a PSP who has accepted the AMIRA agreement. */
    protected function amiraConsentByDefault(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['amira.enabled' => true]);
        $this->user = $this->applicant();
        $this->actingAs($this->user);
    }

    private function answered(?ApplicantDraft $draft): void
    {
        $conversation = AmiraConversation::for($this->user, $draft);
        $conversation->messages()->create(['role' => 'psp', 'body' => 'Tambah 1 tingkat teres']);
        $conversation->messages()->create(['role' => 'amira', 'body' => "Baik.\nPermohonan akan lulus?", 'mentions_outcome' => true, 'model' => 'qwen',
            'sources' => [['library_document_id' => '01JABCDEFGHJKMNPQRSTVWXYZ0', 'version' => 2, 'title' => 'Panduan Anjakan']],
            'proposal' => ['application_type' => 'PB', 'work_type' => 'tambahan', 'storeys' => 3, 'site_conditions' => ['lereng_bukit']]]);
        $conversation->messages()->create(['role' => 'psp', 'body' => 'Lagi']);
        $conversation->messages()->create(['role' => 'amira', 'body' => 'Maaf, saya tidak dapat berbual sekarang.', 'fallback' => true]);
    }

    public function test_enabled_panel_offers_chat_with_retention_note(): void
    {
        $this->get(route('portal.home'))->assertOk()
            ->assertSee('Tanya AMIRA')
            ->assertDontSee('Jangan kongsi maklumat peribadi sensitif')
            ->assertSee('data-amira-chat', false)
            ->assertDontSee('Perbualan AI belum tersedia');
    }

    public function test_disabled_panel_keeps_local_guidance_badge(): void
    {
        config(['amira.enabled' => false]);
        $this->get(route('portal.home'))->assertOk()->assertSee('Perbualan AI belum tersedia')->assertDontSee('data-amira-chat', false);
    }

    public function test_home_thread_shows_labels_sources_officer_line_and_prefill_link(): void
    {
        $this->answered(null);
        $html = $this->get(route('portal.amira.thread'))->assertOk()
            ->assertSee('Jawapan AI')->assertSee('Panduan asas')
            ->assertSee('Keputusan dibuat oleh pegawai MBSP, bukan AMIRA.')
            ->assertSee(route('portal.library.download', '01JABCDEFGHJKMNPQRSTVWXYZ0'), false)
            ->assertSee('Panduan Anjakan (versi 2)')
            ->assertSee('Baik.<br', false)
            ->assertSee('Guna butiran ini')
            ->assertSee('data-pending="false"', false)
            ->getContent();
        $this->assertStringContainsString(e(route('portal.drafts.create', ['prefill' => ['application_type' => 'PB', 'work_type' => 'tambahan', 'storeys' => 3, 'site_conditions' => ['lereng_bukit']]])), $html);
        // Home page renders the same thread inline.
        $this->get(route('portal.home'))->assertSee('Panduan Anjakan (versi 2)');
    }

    public function test_draft_thread_fills_the_form_instead_of_linking(): void
    {
        $draft = $this->telDraft($this->user);
        $this->answered($draft);
        $this->get(route('portal.drafts.amira.thread', $draft))->assertOk()
            ->assertSee('data-amira-apply=', false)
            ->assertDontSee(route('portal.drafts.create'), false);
        $this->get(route('portal.drafts.edit', $draft))->assertOk()->assertSee('Panduan Anjakan (versi 2)');
    }

    public function test_create_page_prefills_only_valid_values(): void
    {
        $this->get(route('portal.drafts.create', ['prefill' => ['application_type' => 'PB', 'work_type' => 'tambahan', 'storeys' => '3',
            'building_category' => 'istana', 'site_conditions' => ['lereng_bukit', 'bulan']]]))->assertOk()
            ->assertSee('<option value="PB" selected>', false)
            ->assertSee('<option value="tambahan" selected>', false)
            ->assertSee('name="storeys" min="1" max="100" step="1" value="3"', false)
            ->assertSee('value="lereng_bukit" checked', false)
            ->assertDontSee('value="istana"', false);
    }
}
