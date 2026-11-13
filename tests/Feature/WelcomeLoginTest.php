<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_returns_all_account_types_home_and_skips_only_the_return_introduction(): void
    {
        foreach (['staff' => 'logout', 'public' => 'portal.logout', 'guest' => 'portal.logout'] as $type => $route) {
            $user = (new User)->forceFill([
                'id' => 1, 'name' => 'Logout test', 'account_type' => $type,
                'role' => $type === 'staff' ? 'pegawai' : 'public_applicant',
                'email_verified_at' => now(),
            ]);
            $this->actingAs($user)->withSession(['private_context' => 'clear-on-logout'])
                ->post(route($route))->assertRedirect(route('home'))
                ->assertSessionHas('skip_welcome_introduction', true)
                ->assertSessionMissing('private_context');
            $this->assertGuest();

            $this->get(route('home'))->assertOk()
                ->assertSee('<html lang="ms"  data-skip-welcome-introduction', false)
                ->assertDontSee('data-reopen-login', false);
            $this->get(route('home'))->assertOk()
                ->assertDontSee('<html lang="ms"  data-skip-welcome-introduction', false);
        }
    }

    public function test_dialog_uses_existing_login_endpoints_and_the_configured_staff_identifier(): void
    {
        config(['services.google.client_id' => 'public-test-client', 'services.google.client_secret' => 'never-render-google-secret']);
        $this->get('/')->assertOk()
            ->assertSee('action="'.route('portal.login.store').'"', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('id="welcome-staff-username" type="email" name="email"', false)
            ->assertSee('href="'.route('portal.forgot').'"', false)
            ->assertSee('href="'.route('portal.register').'"', false)
            ->assertSee('href="'.route('portal.google').'"', false)
            ->assertSee('Teruskan dengan Google')
            ->assertDontSee('never-render-google-secret');

        config(['indecs.staff_auth_driver' => 'mbsp']);

        $this->get('/')->assertOk()
            ->assertSee('id="welcome-staff-username" type="text" name="staff_number"', false)
            ->assertDontSee('id="welcome-staff-username" type="email"', false);
    }

    public function test_failed_public_and_staff_logins_reopen_the_correct_tab_without_rendering_passwords(): void
    {
        foreach (['public' => 'portal.login.store', 'staff' => 'login.store'] as $tab => $route) {
            $this->from('/')->post(route($route), [
                'email' => 'missing-user@example.test',
                'password' => 'never-render-this-password',
                '_welcome_login' => '1',
                '_welcome_tab' => $tab,
            ])->assertRedirect('/')->assertSessionHasErrors('email');

            $this->assertSame('1', session()->getOldInput('_welcome_login'));
            $this->assertTrue(session('errors')->any(), $tab.' errors missing from session');

            $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/')->assertOk();
            $this->assertTrue(str_contains($response->getContent(), 'data-reopen-login'), $tab.' modal did not reopen');
            $response
                ->assertSee('data-login-tab="'.$tab.'"', false)
                ->assertDontSee('never-render-this-password', false);
        }
    }
}
