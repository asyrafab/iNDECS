<?php

namespace Tests\Feature;

use App\Contracts\Identity\MyDigitalIdClient;
use App\Models\ApplicantDraft;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\SandboxIdentityCode;
use App\Services\Identity\VerifiedIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Portal\BuildsApplicants;
use Tests\TestCase;

class SandboxIdentityTest extends TestCase
{
    use BuildsApplicants;
    use RefreshDatabase;

    private User $person;

    private object $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Notification::fake();
        $this->person = User::factory()->create(['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null, 'staff_number' => null]);
        config(['mydigitalid.enabled' => true, 'mydigitalid.enrolment_enabled' => true, 'mydigitalid.login_enabled' => true,
            'mydigitalid.participants' => [$this->person->id], 'mydigitalid.issuer' => 'https://identity.example.test']);
        $this->provider = new class implements MyDigitalIdClient
        {
            public string $subject = 'synthetic-person';

            public bool $offline = false;

            public int $calls = 0;

            public function authorizationUrl(array $transaction): string
            {
                return 'https://identity.example.test/auth?state='.$transaction['state'];
            }

            public function complete(string $code, array $transaction): VerifiedIdentity
            {
                $this->calls++;
                if ($this->offline) {
                    throw new \RuntimeException('Synthetic outage');
                }

                return new VerifiedIdentity('https://identity.example.test', $this->subject, 'Synthetic Official Name', now()->timestamp);
            }
        };
        $this->app->instance(MyDigitalIdClient::class, $this->provider);
    }

    private function code(User $user): string
    {
        return Notification::sent($user, SandboxIdentityCode::class)->last()->code;
    }

    private function pending(): void
    {
        $this->actingAs($this->person);
        $this->post(route('portal.identity.enrol'))->assertRedirect();
        $this->post(route('portal.identity.enrol.verify'), ['code' => $this->code($this->person)])->assertSessionHasNoErrors();
        $this->post(route('portal.identity.link'))->assertRedirect();
        $this->completeCallback()->assertSessionHasNoErrors();
    }

    private function completeCallback()
    {
        $id = session('identity_transaction');
        $payload = json_decode(Crypt::decryptString(DB::table('core.identity_transactions')->where('id', $id)->value('payload')), true);

        return $this->get(route('portal.identity.callback', ['state' => $payload['state'], 'code' => 'synthetic-code']));
    }

    private function linked(): void
    {
        $this->pending();
        $this->post(route('portal.identity.confirm'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('core.external_identities', 1);
        $this->post(route('portal.logout'));
    }

    public function test_existing_account_links_and_both_methods_retain_identity_and_owned_draft(): void
    {
        // Exercise the portal's actual draft fixture and ownership, not a copied account.
        $this->seedPortalTypes();
        $this->actingAs($this->person)->post(route('portal.drafts.store'), $this->data())->assertRedirect();
        $draft = ApplicantDraft::query()->firstOrFail();
        $snapshot = $draft->getAttributes();
        $before = $this->person->only(['id', 'name', 'email', 'password', 'role', 'account_type', 'google_subject']);
        $this->linked();
        $this->assertSame($before, $this->person->fresh()->only(array_keys($before)));
        $this->post(route('portal.identity.login'))->assertRedirect();
        $this->completeCallback()->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($this->person);
        $this->assertSame('mydigitalid', session('sandbox_identity_auth.method'));
        $this->get(route('portal.home'))->assertOk();
        $this->get(route('portal.drafts.edit', $draft))->assertOk();
        $this->post(route('portal.logout'));
        $this->travel(61)->seconds();
        $this->provider->offline = true;
        $calls = $this->provider->calls;
        $this->post(route('portal.identity.otp.request'), ['email' => strtoupper($this->person->email)])->assertRedirect();
        $code = $this->code($this->person);
        $this->post(route('portal.identity.otp.verify'), ['code' => $code])->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($this->person);
        $this->assertSame('email_otp', session('sandbox_identity_auth.method'));
        $this->assertSame($calls, $this->provider->calls);
        $this->assertSame($before, $this->person->fresh()->only(array_keys($before)));
        $this->assertSame($snapshot, $draft->fresh()->getAttributes());
        $this->get(route('portal.drafts.edit', $draft))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->person->id, 'event' => 'identity.linked']);
        $this->assertStringNotContainsString($code, json_encode(DB::table('core.identity_challenges')->get()));
        $this->assertStringNotContainsString($this->person->email, json_encode(DB::table('audit_logs')->where('auditable_type', 'sandbox_identity')->get()));
    }

    public function test_off_and_production_gates_cover_direct_endpoints_and_callbacks(): void
    {
        config(['mydigitalid.enabled' => false]);
        $this->get(route('portal.identity.page'))->assertNotFound();
        $this->post(route('portal.identity.login'))->assertNotFound();
        config(['mydigitalid.enabled' => true]);
        $this->app->instance('env', 'production');
        $this->get(route('portal.identity.page'))->assertNotFound();
        $this->get(route('portal.identity.callback'))->assertNotFound();
    }

    public function test_unknown_unlinked_staff_and_unenrolled_accounts_never_receive_login_codes(): void
    {
        $staff = User::factory()->create();
        config(['mydigitalid.participants' => [$this->person->id, $staff->id]]);
        $count = User::count();
        foreach (['nobody@example.test', $staff->email, $this->person->email] as $email) {
            $this->post(route('portal.identity.otp.request'), compact('email'))->assertSessionHas('status');
        }
        Notification::assertNothingSent();
        $this->assertSame($count, User::count());
        $this->actingAs($staff)->post(route('portal.identity.enrol'))->assertForbidden();
    }

    public function test_bad_state_burns_callback_without_contacting_provider(): void
    {
        $this->post(route('portal.identity.login'));
        $id = session('identity_transaction');
        $this->get(route('portal.identity.callback', ['state' => 'wrong', 'code' => 'code']))->assertSessionHasErrors('identity');
        $this->assertSame(0, $this->provider->calls);
        $this->assertDatabaseHas('core.identity_transactions', ['id' => $id, 'stage' => 'consumed']);
        $this->assertGuest();
    }

    public function test_disabled_pending_callback_never_authenticates(): void
    {
        $this->post(route('portal.identity.login'));
        config(['mydigitalid.login_enabled' => false]);
        $this->completeCallback()->assertSessionHasErrors('identity');
        $this->assertSame(0, $this->provider->calls);
    }

    public function test_stale_or_changed_mailbox_proof_cannot_link(): void
    {
        $this->pending();
        $this->person->forceFill(['email' => 'changed@example.test'])->save();
        $this->post(route('portal.identity.confirm'))->assertSessionHasErrors('identity');
        $this->assertDatabaseCount('core.external_identities', 0);
    }

    public function test_expired_confirmation_and_wrong_account_are_rejected(): void
    {
        $this->pending();
        $this->travel(301)->seconds();
        $this->post(route('portal.identity.confirm'))->assertSessionHasErrors('identity');
        $this->assertDatabaseCount('core.external_identities', 0);
    }

    public function test_identity_conflict_rolls_back_contact_activation(): void
    {
        $this->linked();
        $other = User::factory()->create(['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null]);
        config(['mydigitalid.participants' => [$this->person->id, $other->id]]);
        $this->person = $other;
        $this->pending();
        $this->post(route('portal.identity.confirm'))->assertSessionHasErrors('identity');
        $this->assertDatabaseCount('core.external_identities', 1);
        $this->assertDatabaseCount('core.identity_login_contacts', 1);
    }

    public function test_sso_does_not_create_unlinked_users(): void
    {
        $count = User::count();
        $this->post(route('portal.identity.login'));
        $this->completeCallback()->assertSessionHasErrors('identity');
        $this->assertGuest();
        $this->assertSame($count, User::count());
    }

    public function test_otp_is_single_use_and_browser_bound(): void
    {
        $this->linked();
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
        $code = $this->code($this->person);
        $id = session('identity_login_challenge');
        DB::table('core.identity_challenges')->where('id', $id)->update(['browser' => str_repeat('0', 64)]);
        $this->post(route('portal.identity.otp.verify'), compact('code'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_five_wrong_attempts_survive_request_retries(): void
    {
        $this->linked();
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
        $code = $this->code($this->person);
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('portal.identity.otp.verify'), ['code' => $wrong])->assertSessionHasErrors('identity');
        }
        $this->post(route('portal.identity.otp.verify'), compact('code'))->assertSessionHasErrors('identity');
        $this->assertGuest();
        $this->assertDatabaseHas('core.identity_challenges', ['id' => session('identity_login_challenge'), 'attempts' => 5]);
    }

    public function test_expired_and_replaced_codes_cannot_authenticate(): void
    {
        $this->linked();
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
        $oldId = session('identity_login_challenge');
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.resend'), ['email' => $this->person->email]);
        $this->assertDatabaseHas('core.identity_challenges', ['id' => $oldId, 'active' => false]);
        $code = $this->code($this->person);
        $this->travel(301)->seconds();
        $this->post(route('portal.identity.otp.verify'), compact('code'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_inactive_account_and_revoked_link_cannot_use_issued_code(): void
    {
        $this->linked();
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
        $code = $this->code($this->person);
        DB::table('core.external_identities')->update(['active' => false]);
        $this->post(route('portal.identity.otp.verify'), compact('code'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_pilot_session_expires_without_changing_legacy_sessions(): void
    {
        $this->actingAs($this->person)->withSession(['sandbox_identity_auth' => ['user' => $this->person->id,
            'method' => 'email_otp', 'started' => now()->timestamp, 'last' => now()->timestamp - 1801]])
            ->get(route('portal.identity.page'))->assertRedirect(route('portal.login'));
        $this->assertGuest();
        $this->actingAs($this->person)->get(route('portal.identity.page'))->assertOk();
    }

    public function test_confirm_cannot_follow_a_local_account_swap(): void
    {
        $this->pending();
        $other = User::factory()->create(['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null]);
        config(['mydigitalid.participants' => [$other->id, $this->person->id]]);
        $this->actingAs($other)->post(route('portal.identity.confirm'))->assertSessionHasErrors('identity');
        $this->assertDatabaseCount('core.external_identities', 0);
        $this->assertAuthenticatedAs($other);
    }

    public function test_audit_failure_rolls_back_link_and_contact(): void
    {
        $this->pending();
        AuditLog::creating(function ($event): void {
            if ($event->event === 'identity.linked') {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->post(route('portal.identity.confirm'))->assertSessionHasErrors('identity');
        $this->assertDatabaseCount('core.external_identities', 0);
        $this->assertDatabaseCount('core.identity_login_contacts', 0);
    }

    public function test_case_insensitive_existing_user_email_conflict_prevents_linking(): void
    {
        $this->pending();
        User::factory()->create(['email' => strtoupper($this->person->email)]);
        $this->post(route('portal.identity.confirm'))->assertSessionHasErrors('identity');
        $this->assertDatabaseCount('core.external_identities', 0);
    }

    public function test_enrolment_code_cannot_be_used_for_login(): void
    {
        $this->actingAs($this->person)->post(route('portal.identity.enrol'));
        $id = session('identity_enrolment_challenge');
        $code = $this->code($this->person);
        $this->post(route('portal.logout'));
        $this->withSession(['identity_login_challenge' => $id])->post(route('portal.identity.otp.verify'), compact('code'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_delivery_failure_invalidates_challenge(): void
    {
        $this->linked();
        $this->travel(61)->seconds();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Synthetic failure'));
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email])->assertSessionHas('status');
        $this->assertDatabaseHas('core.identity_challenges', ['id' => session('identity_login_challenge'), 'active' => false]);
        $this->assertGuest();
    }

    public function test_successful_otp_is_consumed_and_cannot_be_replayed(): void
    {
        $this->linked();
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
        $id = session('identity_login_challenge');
        $code = $this->code($this->person);
        $browser = session('identity_browser');
        $this->post(route('portal.identity.otp.verify'), compact('code'))->assertRedirect(route('portal.home'));
        $this->post(route('portal.logout'));
        $this->withSession(['identity_login_challenge' => $id, 'identity_browser' => $browser])->post(route('portal.identity.otp.verify'), compact('code'))->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_resend_does_not_reset_rolling_guess_budget(): void
    {
        $this->linked();
        for ($round = 0; $round < 2; $round++) {
            $this->travel(61)->seconds();
            $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
            $code = $this->code($this->person);
            for ($i = 0; $i < 5; $i++) {
                $this->post(route('portal.identity.otp.verify'), ['code' => $code === '000000' ? '111111' : '000000']);
            }
        }
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
        $this->post(route('portal.identity.otp.verify'), ['code' => $this->code($this->person)])->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_guest_links_without_promotion_and_disabled_enrolment_stays_closed(): void
    {
        $this->person->forceFill(['account_type' => 'guest', 'has_password' => false])->save();
        $this->linked();
        $this->assertSame('guest', $this->person->fresh()->account_type);
        $this->assertFalse($this->person->fresh()->has_password);
        config(['mydigitalid.enrolment_enabled' => false]);
        $this->actingAs($this->person)->post(route('portal.identity.enrol'))->assertNotFound();
    }

    public function test_cooldown_preserves_the_current_challenge_without_sending_again(): void
    {
        $this->linked();
        $this->travel(61)->seconds();
        $this->post(route('portal.identity.otp.request'), ['email' => $this->person->email]);
        $id = session('identity_login_challenge');
        $code = $this->code($this->person);
        $count = Notification::sent($this->person, SandboxIdentityCode::class)->count();
        $this->post(route('portal.identity.otp.resend'), ['email' => $this->person->email]);
        $this->assertSame($id, session('identity_login_challenge'));
        $this->assertSame($count, Notification::sent($this->person, SandboxIdentityCode::class)->count());
        $this->post(route('portal.identity.otp.verify'), compact('code'))->assertRedirect(route('portal.home'));
    }

    public function test_containment_revokes_pilot_sessions_but_does_not_reset_legacy_credentials(): void
    {
        $this->linked();
        $password = $this->person->password;
        $this->post(route('portal.identity.login'));
        $this->completeCallback()->assertRedirect(route('portal.home'));
        $this->artisan('identity:sandbox-revoke', ['user' => $this->person->id])->assertExitCode(0);
        $this->get(route('portal.identity.page'))->assertRedirect(route('portal.login'));
        $this->assertGuest();
        $this->assertSame($password, $this->person->fresh()->password);
        $this->actingAs($this->person)->get(route('portal.identity.page'))->assertOk();
    }

    public function test_mail_notification_is_forced_to_in_memory_transport(): void
    {
        $notification = new SandboxIdentityCode('123456', 'email_login');
        $this->assertSame('array', $notification->toMail($this->person)->mailer);
    }
}
