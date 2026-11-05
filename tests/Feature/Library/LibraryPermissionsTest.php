<?php

namespace Tests\Feature\Library;

use App\Models\User;
use App\Services\PublicIdentityService;
use Database\Seeders\LibrarySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_penolong_pengarah_manages_only_own_department_library(): void
    {
        $pp = User::factory()->create(['department_id' => 'JBANG', 'role' => 'penolong_pengarah']);

        $this->assertTrue($pp->canManageLibrary('JBANG'));
        $this->assertFalse($pp->canManageLibrary('JPBD'));
        $this->assertFalse(User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin'])->canManageLibrary('JBANG'));
        $this->assertFalse(User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer'])->canManageLibrary('JBANG'));
        $this->assertTrue(User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer'])->canViewLibrary());
        $this->assertTrue($pp->canViewLibrary());
    }

    public function test_penolong_pengarah_can_sign_in_with_department_access_but_not_rules(): void
    {
        $pp = User::factory()->create(['department_id' => 'JBANG', 'role' => 'penolong_pengarah']);
        $this->assertTrue($pp->canAccessStaffWorkspace());
        $this->assertTrue($pp->canAccessBangunan());
        $this->assertFalse($pp->canPerformBangunan('documents.configure', 'TEL'));
        $this->assertTrue(User::factory()->create(['department_id' => 'JPBD', 'role' => 'penolong_pengarah'])->canAccessStaffWorkspace());
    }

    public function test_penolong_pengarah_gets_workspace_access_but_no_decision_powers(): void
    {
        $granted = DB::table('core.role_permissions')->where('role_id', 'penolong_pengarah')->pluck('permission_name')->unique()->sort()->values()->all();
        $this->assertSame(['bangunan.application.access', 'bangunan.legacy.view', 'library.manage', 'library.view', 'osc.meeting.view', 'perancang.pkm.access'], $granted);
        // Re-running the seeder (as the migration does on existing databases) removes any earlier wider copy.
        DB::table('core.role_permissions')->insert(['role_id' => 'penolong_pengarah', 'permission_name' => 'osc.agenda.endorse', 'department_id' => 'JBANG', 'application_type_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->seed(LibrarySeeder::class);
        $this->assertFalse(DB::table('core.role_permissions')->where('role_id', 'penolong_pengarah')->where('permission_name', 'osc.agenda.endorse')->exists());
    }

    public function test_public_accounts_cannot_view_library(): void
    {
        $public = app(PublicIdentityService::class)->create('pemohon@example.test', 'Pemohon', 'long-test-password');
        $this->assertFalse($public->canViewLibrary());
    }

    public function test_only_system_admin_can_publish_checklist_rules(): void
    {
        $senior = User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']);
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);

        $this->assertFalse($senior->canPerformBangunan('documents.configure', 'TEL'));
        $this->assertTrue($admin->canPerformBangunan('documents.configure', 'TEL'));
    }
}
