<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApplicationLauncher;
use App\Services\CorePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ApplicationLauncherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_homepage_login_opens_authorised_chooser_and_preserves_intended_urls(): void
    {
        $credentials = ['email' => 'pegawai@indecs.test', 'password' => 'password', '_welcome_login' => '1'];
        $this->post(route('login.store'), $credentials)->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()->assertSee('data-application-launcher', false)
            ->assertSee('href="'.route('perancang.index').'"', false)
            ->assertDontSee('href="'.route('bangunan.index').'"', false)
            ->assertSee('href="'.route('osc.meetings.index').'"', false)
            ->assertDontSee('AIRS')->assertDontSee('data-login-dialog', false);
        $this->get(route('bangunan.index'))->assertForbidden();
        $this->post(route('logout'));
        $this->withSession(['url.intended' => route('perancang.create')])
            ->post(route('login.store'), $credentials)->assertRedirect(route('perancang.create'));
    }

    public function test_promotion_of_18811_is_audited_unscoped_and_idempotent(): void
    {
        $user = User::query()->firstOrFail();
        $user->forceFill(['staff_number' => '18811', 'district' => 'SPT'])->save();
        $password = $user->password;
        $this->artisan('staff:superadmin', ['staff_number' => '18811'])->assertSuccessful();
        $user->refresh();
        $this->assertSame('system_admin', $user->role);
        $this->assertNull($user->district);
        $this->assertSame($password, $user->password);
        foreach (DB::table('core.permissions')->pluck('name') as $permission) {
            $this->assertTrue(app(CorePermissionService::class)->userCan($user, $permission), $permission);
        }
        $this->assertTrue($user->canAccessPkm());
        $this->assertTrue($user->canAccessBangunan());
        $this->assertTrue($user->canAccessOsc());
        $response = $this->actingAs($user)->get(route('home'))->assertOk();
        if (getenv('INDECS_CAPTURE_THEME') === '1') {
            $directory = base_path('.tools/theme-ui');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/application-launcher.html', preg_replace('#http://localhost(?::\d+)?#', 'http://127.0.0.1:8008', $response->getContent()));
        }
        foreach (['iNDECS Perancang', 'iNDECS Bangunan', 'iNDECS Kejuruteraan', 'iNDECS Landskap', 'eOSC+', 'AIRS', 'E-Plan Submission', 'Superadmin'] as $name) {
            $response->assertSee($name);
        }
        $response->assertSee('href="https://spgis.mbsp.gov.my/ePlanSubmission"', false);
        foreach (['perancang.index', 'bangunan.index', 'osc.meetings.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->artisan('staff:superadmin', ['staff_number' => '18811'])->assertSuccessful();
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'staff_superadmin_granted_by_console')->where('auditable_id', $user->id)->count());
    }

    public function test_staff_number_alone_never_grants_administrator_access(): void
    {
        $user = User::query()->firstOrFail();
        $user->forceFill(['staff_number' => '18811'])->save();
        $this->assertFalse($user->canAccessBangunan());
        $this->actingAs($user)->get(route('home'))->assertDontSee('Superadmin')->assertDontSee('AIRS');
        $user->forceFill(['is_active' => false])->save();
        $this->get(route('home'))->assertForbidden();
        $this->artisan('staff:superadmin', ['staff_number' => '18811'])->assertFailed();
        $this->artisan('staff:superadmin', ['staff_number' => '999999'])->assertFailed();
    }

    public function test_public_homepage_login_only_shows_public_account_access(): void
    {
        $user = User::factory()->create(['account_type' => 'public', 'role' => 'public_applicant', 'department_id' => null, 'email_verified_at' => now()]);
        $this->post(route('portal.login.store'), ['email' => $user->email, 'password' => 'password', '_welcome_login' => '1'])->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()->assertSee('Portal Awam')->assertDontSee('iNDECS Bangunan')->assertDontSee('iNDECS Perancang')
            ->assertDontSee('https://spgis.mbsp.gov.my/ePlanSubmission', false);
        $this->assertCount(1, app(ApplicationLauncher::class)->forUser($user));
        $this->get(route('perancang.index'))->assertForbidden();
    }
}
