<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ADR-362: Penolong Pengarah role, department library permissions, and checklist rules
 * restricted to system admin. Idempotent; also run by the 2026_11_05_000100 migration.
 */
final class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();
            DB::table('core.roles')->updateOrInsert(['id' => 'penolong_pengarah'],
                ['label' => 'Penolong Pengarah', 'account_type' => 'staff', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach (['library.manage' => 'Urus perpustakaan jabatan', 'library.view' => 'Lihat perpustakaan jabatan'] as $name => $label) {
                DB::table('core.permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => $now, 'updated_at' => $now]);
            }
            $this->grant('penolong_pengarah', 'library.manage');
            // System admin holds every permission (superadmin invariant); User::canManageLibrary still
            // requires the Penolong Pengarah role, so the owner's "only Penolong Pengarah" rule stands.
            $this->grant('system_admin', 'library.manage');
            // A Penolong Pengarah works in their department like its senior officer (minus rule publishing),
            // so they can sign in to the staff workspace and reach the library.
            DB::table('core.role_permissions')->whereIn('role_id', ['senior_building_officer', 'senior_planning_officer'])
                ->where('permission_name', '!=', 'bangunan.application.documents.configure')->get()
                ->each(function (object $row): void {
                    $scope = ['role_id' => 'penolong_pengarah', 'permission_name' => $row->permission_name,
                        'department_id' => $row->department_id, 'application_type_id' => $row->application_type_id];
                    if (! DB::table('core.role_permissions')->where($scope)->exists()) {
                        DB::table('core.role_permissions')->insert($scope + ['created_at' => now(), 'updated_at' => now()]);
                    }
                });
            foreach (DB::table('core.roles')->where('account_type', 'staff')->pluck('id') as $role) {
                $this->grant($role, 'library.view');
            }
            // Checklist rules (document catalogue) are published by system admin only.
            DB::table('core.role_permissions')->where('permission_name', 'bangunan.application.documents.configure')
                ->where('role_id', '!=', 'system_admin')->delete();
        });
    }

    private function grant(string $role, string $permission): void
    {
        $scope = ['role_id' => $role, 'permission_name' => $permission, 'department_id' => null, 'application_type_id' => null];
        // On a fresh database the migration runs before roles are seeded; DatabaseSeeder runs this again.
        if (DB::table('core.roles')->where('id', $role)->exists() && ! DB::table('core.role_permissions')->where($scope)->exists()) {
            DB::table('core.role_permissions')->insert($scope + ['created_at' => now(), 'updated_at' => now()]);
        }
    }
}
