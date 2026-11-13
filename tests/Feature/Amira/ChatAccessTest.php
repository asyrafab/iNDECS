<?php

namespace Tests\Feature\Amira;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

class ChatAccessTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

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
        Queue::fake();
    }

    public function test_foreign_draft_is_404(): void
    {
        $draft = $this->telDraft($this->applicant('pemilik@example.test'));
        $this->actingAs($this->applicant('lain@example.test'));
        $this->get(route('portal.drafts.amira.thread', $draft))->assertNotFound();
        $this->postJson(route('portal.drafts.amira.ask', $draft), ['question' => 'Hai'])->assertNotFound();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('portal.amira.thread'))->assertRedirect();
        $this->postJson(route('portal.amira.ask'), ['question' => 'Hai'])->assertStatus(401);
    }

    public function test_ask_returns_thread_with_escaped_question(): void
    {
        $this->actingAs($this->applicant());
        $this->postJson(route('portal.amira.ask'), ['question' => '<b>Dokumen</b> apa?'])
            ->assertCreated()
            ->assertSee('&lt;b&gt;Dokumen&lt;/b&gt; apa?', false)
            ->assertDontSee('<b>Dokumen</b>', false)
            ->assertSee('data-pending="true"', false)
            ->assertSee('AMIRA sedang menaip');
    }

    public function test_second_question_while_pending_is_409(): void
    {
        $owner = $this->applicant();
        $draft = $this->telDraft($owner);
        $this->actingAs($owner);
        $this->postJson(route('portal.drafts.amira.ask', $draft), ['question' => 'Satu'])->assertCreated();
        $this->postJson(route('portal.drafts.amira.ask', $draft), ['question' => 'Dua'])
            ->assertStatus(409)->assertJson(['message' => 'Tunggu AMIRA selesai menjawab.']);
    }

    public function test_empty_question_is_422(): void
    {
        $this->actingAs($this->applicant());
        $this->postJson(route('portal.amira.ask'), ['question' => ' '])->assertStatus(422)->assertJsonValidationErrors('question');
    }
}
