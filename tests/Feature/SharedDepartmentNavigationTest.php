<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SharedDepartmentNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public static function sharedPages(): array
    {
        $cases = [];
        foreach (['perancang' => 'Planning', 'bangunan' => 'Building', 'kejuruteraan' => 'Engineering', 'landskap' => 'Landscape'] as $department => $name) {
            foreach (['tasks.index' => 'Tugasan pegawai', 'library.index' => 'Perpustakaan jabatan'] as $route => $active) {
                $cases[] = [$department, $name, $route, $active];
            }
        }

        return $cases;
    }

    #[DataProvider('sharedPages')]
    public function test_shared_pages_retain_each_department_and_the_same_common_functions(string $department, string $name, string $route, string $activeLabel): void
    {
        $user = User::factory()->create(['role' => 'system_admin', 'department_id' => 'JPBD']);
        $this->actingAs($user)->get(route($department.'.dashboard'))->assertOk();
        $response = $this->get(route($route))->assertOk()->assertSessionHas('workspace.department.'.$user->id, $department);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertStringContainsString($name, $xpath->query('//details[@class="workspace-switcher"]/summary')->item(0)->textContent);
        foreach ([$department.'.dashboard', $department.'.index'] as $target) {
            $this->assertSame(1, $xpath->query('//nav[@class="primary-nav"]//a[@href="'.route($target).'"]')->length);
        }
        $labels = [];
        foreach ($xpath->query('//nav[@class="primary-nav"]//*[contains(concat(" ",normalize-space(@class)," ")," nav-link ")]') as $element) {
            if (trim($element->textContent) !== 'Carian Rekod Warisan') {
                $labels[] = trim($element->textContent);
            }
        }
        $this->assertSame(['Dashboard jabatan', 'Permohonan', 'Daftar permohonan', 'Tugasan pegawai', 'Daftar Mesyuarat', 'Perpustakaan jabatan', 'Panduan AMIRA'], $labels);
        $active = $xpath->query('//nav[@class="primary-nav"]//a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame($activeLabel, trim($active->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//details//a[@aria-current="location"]')->length);
        if ($department === 'kejuruteraan') {
            $this->assertSame(1, $xpath->query('//nav[@class="primary-nav"]//span[@aria-disabled="true" and @title="Pendaftaran pegawai belum tersedia"]')->length);
        }
    }

    public function test_context_survives_a_chain_of_shared_pages_and_library_filters(): void
    {
        $user = User::factory()->create(['role' => 'system_admin', 'department_id' => 'JPBD']);
        $this->actingAs($user)->get(route('bangunan.dashboard'))->assertOk();
        foreach ([route('tasks.index'), route('library.index', ['department' => 'JPBD']), route('osc.meetings.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('href="'.route('bangunan.index').'"', false)
                ->assertSessionHas('workspace.department.'.$user->id, 'bangunan');
        }
    }

    public function test_library_only_staff_do_not_receive_department_links(): void
    {
        $user = User::factory()->create(['role' => 'penolong_pengarah', 'department_id' => 'JPBD']);
        DB::table('core.role_permissions')->where('role_id', $user->role)->where('permission_name', 'not like', 'library.%')->delete();
        $this->actingAs($user)->withSession(['workspace.department.'.$user->id => 'bangunan'])->get(route('library.index'))
            ->assertOk()->assertSee('Ruang kerja bersama')->assertSee('aria-disabled="true"', false)
            ->assertDontSee('href="'.route('perancang.dashboard').'"', false)
            ->assertDontSee('href="'.route('bangunan.dashboard').'"', false)
            ->assertDontSee('href="'.route('tasks.index').'"', false);
    }
}
