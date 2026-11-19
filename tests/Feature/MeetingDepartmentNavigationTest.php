<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MeetingDepartmentNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public static function departments(): array
    {
        return [['perancang', 'Planning'], ['bangunan', 'Building'], ['kejuruteraan', 'Engineering'], ['landskap', 'Landscape']];
    }

    #[DataProvider('departments')]
    public function test_meeting_pages_keep_the_last_department_menu(string $department, string $label): void
    {
        $user = User::factory()->create(['role' => 'system_admin', 'department_id' => 'JPBD']);
        $this->actingAs($user)->get(route($department.'.dashboard'))->assertOk()
            ->assertSessionHas('workspace.department.'.$user->id, $department);
        $response = $this->get(route('osc.meetings.index'))->assertOk()->assertDontSee('Semua aplikasi');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertStringContainsString($label, $xpath->query('//details[@class="workspace-switcher"]/summary')->item(0)->textContent);
        foreach ([$department.'.dashboard', $department.'.index'] as $route) {
            $this->assertSame(1, $xpath->query('//nav[@class="primary-nav"]//a[@href="'.route($route).'"]')->length, $route);
        }
        $active = $xpath->query('//nav[@class="primary-nav"]//a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame('Daftar Mesyuarat', trim($active->item(0)->textContent));
    }

    public function test_switching_departments_updates_the_remembered_menu(): void
    {
        $user = User::factory()->create(['role' => 'system_admin', 'department_id' => 'JPBD']);
        $this->actingAs($user)->get(route('bangunan.dashboard'))->assertOk();
        $this->get(route('perancang.dashboard'))->assertOk();
        $this->get(route('osc.meetings.index'))->assertOk()->assertSee('Permohonan PKM')
            ->assertSessionHas('workspace.department.'.$user->id, 'perancang');
    }

    public function test_unavailable_or_invalid_remembered_departments_do_not_grant_access(): void
    {
        $user = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        foreach (['bangunan', ['invalid']] as $saved) {
            $this->actingAs($user)->withSession(['workspace.department.'.$user->id => $saved])
                ->get(route('osc.meetings.index'))->assertOk()->assertSee('Permohonan PKM')
                ->assertDontSee('href="'.route('bangunan.dashboard').'"', false);
        }
    }

    public function test_revoking_department_access_removes_its_remembered_navigation(): void
    {
        $user = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->actingAs($user)->get(route('bangunan.dashboard'))->assertOk();
        DB::table('core.role_permissions')->where('role_id', 'building_officer')
            ->whereIn('permission_name', ['bangunan.application.access', 'bangunan.legacy.view'])->delete();
        $this->get(route('osc.meetings.index'))->assertOk()
            ->assertDontSee('href="'.route('bangunan.dashboard').'"', false)
            ->assertDontSee('href="'.route('bangunan.create').'"', false)
            ->assertDontSee('href="'.route('perancang.dashboard').'"', false);
    }

    public function test_direct_meeting_entry_does_not_reuse_another_users_department(): void
    {
        $first = User::factory()->create(['role' => 'system_admin', 'department_id' => 'JBANG']);
        $second = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->actingAs($first)->get(route('bangunan.dashboard'))->assertOk();
        $this->actingAs($second)->get(route('osc.meetings.index'))->assertOk()->assertSee('Permohonan PKM')
            ->assertDontSee('href="'.route('bangunan.dashboard').'"', false);
    }
}
