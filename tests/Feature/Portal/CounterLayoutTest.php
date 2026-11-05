<?php

namespace Tests\Feature\Portal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterLayoutTest extends TestCase
{
    use BuildsApplicants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortalTypes();
    }

    public function test_home_renders_counter_with_amira_and_file_side(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('class="counter"', false)
            ->assertSee('data-amira-bubble', false)
            ->assertSee('data-mood="calm"', false)
            ->assertSee('Pegawai Kaunter Maya')
            ->assertSee('Perbualan AI belum tersedia')
            ->assertSee('AMIRA membantu. Pegawai memutuskan.')
            ->assertDontSee('id="amira-panel"', false);
    }

    public function test_counter_has_phone_bar_controlling_amira_sheet(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('data-amira-sheet-open', false)
            ->assertSee('aria-controls="amira-counter"', false)
            ->assertSee('data-amira-sheet-close', false)
            ->assertSee('Tanya AMIRA');
    }

    public function test_amira_controls_stay_inert_without_javascript(): void
    {
        // Without JS the composer must not submit a GET that reloads the editor and leaks the question into the URL.
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('<div class="amira-counter-interactive" data-amira-interactive hidden>', false);
    }

    public function test_amira_is_reachable_on_phones_without_javascript(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('<noscript><style>@media (max-width: 900px) { .counter-amira { display: flex; position: static;', false);
    }

    public function test_empty_board_copy_does_not_promise_a_conversation(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertDontSee('Ceritakan kepada AMIRA')
            ->assertSee('Mulakan permohonan baharu untuk menyediakan draf maklumat awal.');
    }

    public function test_pages_without_counter_keep_the_amira_drawer(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.account'))->assertOk()
            ->assertSee('id="amira-panel"', false)
            ->assertDontSee('class="counter"', false);
    }
}
