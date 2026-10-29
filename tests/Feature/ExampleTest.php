<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed();
        $this->actingAs(User::query()->firstOrFail());

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('iNDECS')
            ->assertSee('Ringkasan PKM')
            ->assertDontSee('SKPB');
    }

    public function test_guests_can_choose_access_from_the_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('Urusan Pembangunan Anda,')
            ->assertSee('Dalam Satu Tempat')
            ->assertSee('Penjejakan status awam belum tersedia.')
            ->assertSee('href="'.route('portal.login').'"', false)
            ->assertSee('href="'.route('portal.register').'"', false)
            ->assertSee('href="'.route('portal.email').'"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('href="'.route('bangunan.index').'"', false)
            ->assertDontSee('href="'.route('perancang.index').'"', false);
        $this->get(route('bangunan.index'))->assertRedirect(route('login'));
        $this->get(route('perancang.index'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Log masuk ke iNDECS');
    }

    public function test_an_active_planning_officer_can_log_in(): void
    {
        $this->seed();

        $this->post(route('login.store'), [
            'email' => 'pegawai@indecs.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
