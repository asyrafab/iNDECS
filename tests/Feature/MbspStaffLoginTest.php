<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MbspStaffLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        User::query()->firstOrFail()->forceFill(['staff_number' => '12345'])->save();
        config([
            'indecs.staff_auth_driver' => 'mbsp',
            'services.mbsp_staff' => [
                'url' => 'https://staff.example.test/login',
                'basic_username' => 'test-client',
                'basic_password' => 'test-secret',
                'api_key' => 'test-key',
                'api_token' => 'test-token',
                'password_mode' => 'md5',
            ],
        ]);
        Http::preventStrayRequests();
    }

    public function test_valid_staff_logs_in_with_documented_request_and_no_password_storage(): void
    {
        $hash = User::query()->firstOrFail()->password;
        Http::fake(['*' => Http::response($this->profile())]);
        $this->attempt()->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame($hash, User::query()->firstOrFail()->password);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://staff.example.test/login'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-client:test-secret'))
            && str_starts_with($request->header('Content-Type')[0], 'application/x-www-form-urlencoded')
            && $request->hasHeader('Accept', '*/*')
            && $request['test-key'] === 'test-token'
            && $request['nostaff'] === '12345'
            && $request['password'] === md5('staff-password'));
    }

    public function test_null_credentials_and_outages_never_fall_back_to_local_password(): void
    {
        Http::fake(['*' => Http::sequence()->push('null')->push('unavailable', 503)]);
        foreach (range(1, 2) as $attempt) {
            $this->attempt('password')->assertSessionHasErrors('staff_number');
            $this->assertGuest();
        }
    }

    public function test_expired_password_and_identity_mismatch_are_denied(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->profile(['FLAGPASSWORD' => 'expire']))
            ->push($this->profile(['NOSTAFF' => '99999']))
            ->push($this->profile(['FLAGPASSWORD' => 'unknown']))]);
        foreach (range(1, 3) as $attempt) {
            $this->attempt()->assertSessionHasErrors('staff_number');
            $this->assertGuest();
        }
    }

    public function test_unlinked_inactive_and_unauthorised_users_are_denied(): void
    {
        Http::fake(['*' => Http::response($this->profile())]);
        $user = User::query()->firstOrFail();
        foreach ([['staff_number' => null], ['staff_number' => '12345', 'is_active' => false],
            ['is_active' => true, 'role' => 'unassigned']] as $attributes) {
            $user->forceFill($attributes)->save();
            $this->attempt()->assertSessionHasErrors([
                'staff_number' => 'Pengesahan MBSP berjaya, tetapi akses iNDECS belum dibenarkan. Sila hubungi pentadbir iNDECS.',
            ]);
            $this->assertGuest();
        }
    }

    public function test_mbsp_rejection_does_not_disclose_local_account_access(): void
    {
        Http::fake(['*' => Http::response('null')]);
        $user = User::query()->firstOrFail();

        foreach (['12345', null] as $staffNumber) {
            $user->forceFill(['staff_number' => $staffNumber])->save();
            $this->attempt()->assertSessionHasErrors([
                'staff_number' => 'MBSP tidak mengesahkan nombor kakitangan atau kata laluan ini. Sila semak maklumat log masuk MBSP anda.',
            ]);
            $this->assertGuest();
        }
    }

    public function test_connection_failure_and_malformed_json_are_safe_validation_errors(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->attempt()->assertSessionHasErrors('staff_number');
        $this->assertGuest();
        Http::fake(['*' => Http::response('<html>bad gateway</html>')]);
        $this->attempt()->assertSessionHasErrors('staff_number');
        $this->assertGuest();
    }

    public function test_lockout_stops_further_api_requests(): void
    {
        Http::fake(['*' => Http::response('null')]);
        foreach (range(1, 6) as $attempt) {
            $this->attempt()->assertSessionHasErrors('staff_number');
        }
        Http::assertSentCount(5);
    }

    public function test_mbsp_login_view_uses_staff_number_and_no_remember_option(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Nombor kakitangan')
            ->assertDontSee('name="remember"', false)->assertDontSee('name="email"', false);
    }

    public function test_staff_link_command_is_audited_and_refuses_reassignment(): void
    {
        $user = User::query()->firstOrFail();
        $user->forceFill(['staff_number' => null])->save();
        $this->artisan('staff:link', ['email' => $user->email, 'staff_number' => '12345'])->assertSuccessful();
        $this->assertDatabaseHas('audit_logs', ['event' => 'staff_account_linked_by_console', 'auditable_id' => $user->id]);
        $this->artisan('staff:link', ['email' => $user->email, 'staff_number' => '99999'])->assertFailed();
        $this->assertSame('12345', $user->fresh()->staff_number);
    }

    public function test_staff_link_reports_missing_account_and_power_shell_escape_mistake(): void
    {
        $this->artisan('staff:link', ['email' => 'missing@example.test', 'staff_number' => '12345'])
            ->assertFailed()
            ->expectsOutput('Akaun iNDECS untuk e-mel ini belum wujud. Jalankan staff:approve dahulu.');

        $this->artisan('staff:link', ['email' => 'pegawai\@indecs.test', 'staff_number' => '12345'])
            ->assertFailed()
            ->expectsOutput('E-mel tidak sah. Dalam PowerShell, gunakan @ tanpa backslash.');
    }

    public function test_staff_approve_creates_audited_shell_account_and_links_number(): void
    {
        $this->artisan('staff:approve', [
            'email' => 'mohammadasyraf@mbsp.gov.my',
            'staff_number' => '18811',
            '--name' => 'Mohammad Asyraf',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'mohammadasyraf@mbsp.gov.my')->firstOrFail();
        $this->assertSame('18811', $user->staff_number);
        $this->assertSame('JPBD', $user->department_id);
        $this->assertSame('planning_officer', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('staff', $user->account_type);
        $this->assertFalse($user->has_password);
        $this->assertTrue($user->canAccessPkm());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'event' => 'staff_account_approved_by_console',
        ]);
    }

    public function test_staff_approve_rejects_roles_without_pkm_permission(): void
    {
        $this->artisan('staff:approve', [
            'email' => 'mohammadasyraf@mbsp.gov.my',
            'staff_number' => '18811',
            '--role' => 'unassigned',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'mohammadasyraf@mbsp.gov.my']);
    }

    public function test_missing_service_configuration_sends_no_request(): void
    {
        config(['services.mbsp_staff.api_token' => '']);
        Http::fake();
        $this->attempt()->assertSessionHasErrors('staff_number');
        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_raw_mode_and_hexadecimal_passwords_have_explicit_semantics(): void
    {
        Http::fake(['*' => Http::response($this->profile())]);
        $password = str_repeat('a', 32);
        $this->attempt($password)->assertRedirect(route('dashboard'));
        Http::assertSent(fn (Request $request) => $request['password'] === md5($password));
        $this->post(route('logout'));
        config(['services.mbsp_staff.password_mode' => 'raw']);
        $this->attempt($password)->assertRedirect(route('dashboard'));
        Http::assertSent(fn (Request $request) => $request['password'] === $password);
    }

    public function test_staff_check_login_can_verify_postman_md5_without_rehashing(): void
    {
        Http::fake(['*' => Http::response($this->profile())]);
        $hash = str_repeat('a', 32);

        $this->artisan('staff:check-login', ['staff_number' => '12345', '--prehashed-md5' => true])
            ->expectsQuestion('Nilai MD5 dari Postman', $hash)
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request['password'] === $hash);
    }

    public function test_staff_check_login_rejects_invalid_postman_md5(): void
    {
        Http::fake();

        $this->artisan('staff:check-login', ['staff_number' => '12345', '--prehashed-md5' => true])
            ->expectsQuestion('Nilai MD5 dari Postman', 'not-a-hash')
            ->assertFailed();

        Http::assertNothingSent();
    }

    private function profile(array $overrides = []): array
    {
        return ['status' => 'success', 'data' => [array_replace([
            'NOSTAFF' => '12345', 'NAMA' => 'Test Officer', 'EMEL' => null,
            'FLAGPASSWORD' => 'valid', 'DIFFDAY' => '7',
        ], $overrides)]];
    }

    private function attempt(string $password = 'staff-password')
    {
        return $this->post(route('login.store'), ['staff_number' => '12345', 'password' => $password]);
    }
}
