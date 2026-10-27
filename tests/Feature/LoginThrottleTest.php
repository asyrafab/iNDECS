<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_failures_block_correct_credentials_until_expiry(): void
    {
        $this->seed();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login('wrong-password')->assertSessionHasErrors('email');
        }

        $this->login('password')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->login('password')->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_success_clears_previous_failures(): void
    {
        $this->seed();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->login('wrong-password');
        }

        $this->login('password')->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->login('wrong-password');
        }

        $this->login('password')->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_email_case_changes_do_not_bypass_lockout(): void
    {
        $this->seed();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login('wrong-password', 'PEGAWAI@INDECS.TEST');
        }

        $this->login('password')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_accounts_cannot_log_in(): void
    {
        $this->seed();
        User::query()->update(['is_active' => false]);

        $this->login('password')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function login(string $password, string $email = 'pegawai@indecs.test')
    {
        return $this->post(route('login.store'), compact('email', 'password'));
    }
}
