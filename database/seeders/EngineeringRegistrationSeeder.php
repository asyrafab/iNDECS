<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EngineeringRegistrationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['access' => 'Akses permohonan Kejuruteraan', 'register' => 'Daftar permohonan Kejuruteraan'] as $action => $label) {
                DB::table('core.permissions')->insertOrIgnore(['name' => 'kejuruteraan.application.'.$action, 'label' => $label, 'created_at' => now(), 'updated_at' => now()]);
                $this->grant('system_admin', 'kejuruteraan.application.'.$action, null, null);
            }
            foreach (['PKT', 'PKP'] as $type) {
                DB::table('core.application_types')->where('id', $type)->where('primary_department_id', 'JKEJ')->update(['is_active' => true]);
                DB::table('core.workflow_templates')->insertOrIgnore(['application_type_id' => $type, 'version' => 1,
                    'initial_stage' => 'registration', 'initial_status' => 'submitted', 'registration_permission' => 'kejuruteraan.application.register',
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                foreach (['engineering_officer', 'senior_engineering_officer'] as $role) {
                    foreach (['access', 'register'] as $action) {
                        $this->grant($role, 'kejuruteraan.application.'.$action, 'JKEJ', $type);
                    }
                    foreach ($role === 'senior_engineering_officer' ? ['view', 'work', 'assign'] : ['view', 'work'] as $action) {
                        if (DB::table('core.permissions')->where('name', 'core.tasks.'.$action)->exists()) {
                            $this->grant($role, 'core.tasks.'.$action, 'JKEJ', $type);
                        }
                    }
                }
            }
        });
    }

    private function grant(string $role, string $permission, ?string $department, ?string $type): void
    {
        if (! DB::table('core.roles')->where('id', $role)->exists()) {
            return;
        }
        $scope = ['role_id' => $role, 'permission_name' => $permission, 'department_id' => $department, 'application_type_id' => $type];
        if (! DB::table('core.role_permissions')->where($scope)->exists()) {
            DB::table('core.role_permissions')->insert($scope + ['created_at' => now(), 'updated_at' => now()]);
        }
    }
}
