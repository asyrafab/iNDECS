<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PublicAccessLink;
use App\Services\PublicIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class PublicAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_registration_creates_only_public_account_and_requires_verification(): void
    {
        $this->post(route('portal.register.store'), [
            'name' => 'Public Applicant', 'email' => 'PUBLIC@example.test',
            'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password',
            'role' => 'system_admin', 'department_id' => 'JPBD', 'account_type' => 'staff',
        ])->assertRedirect(route('portal.login'));
        $user = User::query()->firstOrFail();
        $this->assertSame('public', $user->account_type);
        $this->assertSame('public_applicant', $user->role);
        $this->assertNull($user->department_id);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
        $this->assertGuest();
        $this->post(route('portal.login.store'), ['email' => $user->email, 'password' => 'a-long-test-password'])->assertSessionHasErrors('email');
        $token = $this->tokenFor($user, 'verify');
        $this->post(route('portal.link.consume', ['verify', $token]))->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get(route('perancang.index'))->assertForbidden();
    }

    public function test_guest_email_link_is_hashed_purpose_bound_and_single_use(): void
    {
        $this->post(route('portal.email.send', 'login'), ['email' => 'guest@example.test'])->assertRedirect();
        $user = User::query()->firstOrFail();
        $this->assertSame('guest', $user->account_type);
        $this->assertFalse($user->has_password);
        $token = $this->tokenFor($user, 'login');
        $this->assertSame(hash('sha256', $token), DB::table('core.public_access_tokens')->value('token_hash'));
        $this->get(route('portal.link.show', ['login', $token]))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest();
        $this->post(route('portal.link.consume', ['verify', $token]))->assertSessionHasErrors('email');
        $this->post(route('portal.link.consume', ['login', $token]))->assertRedirect(route('portal.home'));
        $this->post(route('portal.logout'));
        $this->post(route('portal.link.consume', ['login', $token]))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_expired_and_superseded_links_are_denied(): void
    {
        $user = $this->publicUser();
        $identity = app(PublicIdentityService::class);
        $identity->issue($user, 'login');
        $first = $this->tokenFor($user, 'login');
        $identity->issue($user, 'login');
        $this->post(route('portal.link.consume', ['login', $first]))->assertSessionHasErrors('email');
        $latest = Notification::sent($user, PublicAccessLink::class)->last()->token;
        $this->travel(16)->minutes();
        $this->post(route('portal.link.consume', ['login', $latest]))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_cannot_use_public_password_email_or_reset_routes(): void
    {
        $this->seed();
        $user = User::query()->firstOrFail();
        $this->post(route('portal.login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        foreach (['login', 'reset', 'verify'] as $purpose) {
            $this->post(route('portal.email.send', $purpose), ['email' => $user->email])->assertRedirect();
        }
        Notification::assertNothingSent();
        $this->assertGuest();
        $this->actingAs($user)->get(route('portal.home'))->assertForbidden();
    }

    public function test_guest_can_claim_account_without_changing_privileges(): void
    {
        $user = $this->publicUser('guest', false);
        $this->actingAs($user)->post(route('portal.claim'), [
            'name' => 'Applicant', 'password' => 'another-long-password', 'password_confirmation' => 'another-long-password',
            'role' => 'system_admin',
        ])->assertRedirect();
        $this->assertTrue($user->fresh()->has_password);
        $this->assertSame('public', $user->fresh()->account_type);
        $this->assertSame('public_applicant', $user->fresh()->role);
    }

    public function test_password_reset_changes_password_and_revokes_other_links(): void
    {
        $user = $this->publicUser();
        $identity = app(PublicIdentityService::class);
        $identity->issue($user, 'login');
        $identity->issue($user, 'reset');
        $token = $this->tokenFor($user, 'reset');
        $this->post(route('portal.link.consume', ['reset', $token]), [
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ])->assertRedirect(route('portal.home'));
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertSame(0, DB::table('core.public_access_tokens')->count());
    }

    public function test_email_access_cannot_activate_an_unverified_registrants_password(): void
    {
        $user = $this->publicUser();
        $user->forceFill(['email_verified_at' => null])->save();
        app(PublicIdentityService::class)->issue($user, 'login');
        $this->post(route('portal.link.consume', ['login', $this->tokenFor($user, 'login')]))->assertRedirect();
        $this->assertFalse($user->fresh()->has_password);
        $this->assertFalse(Hash::check('a-long-test-password', $user->fresh()->password));
    }

    public function test_google_creates_verified_public_account_without_storing_access_tokens(): void
    {
        $this->fakeGoogle('google-123', 'google@example.test');
        $this->get(route('portal.google.callback'))->assertRedirect(route('portal.home'));
        $user = User::query()->firstOrFail();
        $this->assertSame('google-123', $user->google_subject);
        $this->assertTrue($user->isPublicAccount());
        $this->assertFalse($user->has_password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_does_not_auto_link_an_existing_email(): void
    {
        $user = $this->publicUser();
        $this->fakeGoogle('google-123', $user->email);
        $this->get(route('portal.google.callback'))->assertRedirect(route('portal.login'))->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->google_subject);
        $this->assertGuest();
    }

    public function test_google_state_errors_are_denied(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get(route('portal.google.callback'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_google_unverified_email_is_denied(): void
    {
        $this->fakeGoogle('unverified-google', 'google@example.test', false);
        $this->get(route('portal.google.callback'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_cannot_authenticate_staff_even_with_a_linked_subject(): void
    {
        $this->seed();
        $staff = User::query()->firstOrFail();
        $staff->forceFill(['google_subject' => 'staff-google'])->save();
        $this->fakeGoogle('staff-google', $staff->email);
        $this->get(route('portal.google.callback'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_duplicate_registration_does_not_replace_a_password_or_grant_access(): void
    {
        $user = $this->publicUser();
        $hash = $user->password;
        $this->post(route('portal.register.store'), [
            'name' => 'Another person', 'email' => $user->email,
            'password' => 'different-password', 'password_confirmation' => 'different-password',
        ])->assertRedirect(route('portal.login'));
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_inactive_public_users_cannot_redeem_email_links(): void
    {
        $user = $this->publicUser();
        app(PublicIdentityService::class)->issue($user, 'login');
        $user->forceFill(['is_active' => false])->save();
        $this->post(route('portal.link.consume', ['login', $this->tokenFor($user, 'login')]))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_verified_account_owner_can_explicitly_link_google(): void
    {
        $user = $this->publicUser();
        $this->fakeGoogle('google-123', $user->email);
        $this->actingAs($user)->withSession(['google_link_user' => $user->id])
            ->get(route('portal.google.callback'))->assertRedirect(route('portal.home'));
        $this->assertSame('google-123', $user->fresh()->google_subject);
    }

    public function test_all_public_entry_pages_render(): void
    {
        foreach (['portal.login', 'portal.register', 'portal.email', 'portal.forgot'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    private function publicUser(string $type = 'public', bool $hasPassword = true): User
    {
        $user = app(PublicIdentityService::class)->create('public@example.test', 'Applicant', $hasPassword ? 'a-long-test-password' : null, $type);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function tokenFor(User $user, string $purpose): string
    {
        return Notification::sent($user, PublicAccessLink::class)->first(fn ($notification) => $notification->purpose === $purpose)->token;
    }

    private function fakeGoogle(string $subject, string $email, bool $verified = true): void
    {
        $google = (new GoogleUser)->setRaw(['email_verified' => $verified])->map([
            'id' => $subject, 'email' => $email, 'name' => 'Google Applicant',
        ]);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
