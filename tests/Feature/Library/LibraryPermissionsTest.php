<?php

namespace Tests\Feature\Library;

use App\Models\User;
use App\Services\PublicIdentityService;
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
