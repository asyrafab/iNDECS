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
        if (! DB::table('core.role_permissions')->where($scope)->exists()) {
            DB::table('core.role_permissions')->insert($scope + ['created_at' => now(), 'updated_at' => now()]);
        }
    }
}
