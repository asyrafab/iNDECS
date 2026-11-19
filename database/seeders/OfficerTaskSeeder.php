<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BangunanApplication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class OfficerTaskSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['view' => 'Lihat tugasan pegawai', 'work' => 'Laksanakan tugasan sendiri', 'assign' => 'Agih dan urus tugasan jabatan'] as $action => $label) {
                DB::table('core.permissions')->updateOrInsert(['name' => 'core.tasks.'.$action], ['label' => $label, 'created_at' => now(), 'updated_at' => now()]);
                $this->grant('system_admin', $action, null, null);
            }
            $modules = [
                ['JPBD', 'PKM', 'perancang.pkm.access', ['planning_officer', 'senior_planning_officer'], 'senior_planning_officer'],
                ['JLAND', 'PL', 'landskap.pl.access', ['landscape_officer', 'senior_landscape_officer'], 'senior_landscape_officer'],
                ['JKEJ', 'LJUP', 'kejuruteraan.ljup.access', ['engineering_officer', 'senior_engineering_officer'], 'senior_engineering_officer'],
            ];
            foreach (BangunanApplication::TYPES as $type) {
                $modules[] = ['JBANG', $type, 'bangunan.application.access', ['building_officer', 'senior_building_officer'], 'senior_building_officer'];
            }
            foreach ($modules as [$department, $type, $access, $roles, $supervisor]) {
                foreach ($roles as $role) {
                    if (! DB::table('core.role_permissions')->where('role_id', $role)->where('permission_name', $access)->exists()) {
                        continue;
                    }
                    $this->grant($role, 'view', $department, $type);
                    $this->grant($role, 'work', $department, $type);
                    if ($role === $supervisor) {
                        $this->grant($role, 'assign', $department, $type);
                    }
                }
            }
        });
    }

    private function grant(string $role, string $action, ?string $department, ?string $type): void
    {
        if (! DB::table('core.roles')->where('id', $role)->exists()) {
            return;
        }
        $scope = ['role_id' => $role, 'permission_name' => 'core.tasks.'.$action, 'department_id' => $department, 'application_type_id' => $type];
        if (! DB::table('core.role_permissions')->where($scope)->exists()) {
            DB::table('core.role_permissions')->insert($scope + ['created_at' => now(), 'updated_at' => now()]);
        }
    }
}
