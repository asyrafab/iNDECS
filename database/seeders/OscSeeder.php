<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OscSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ([
            ['id' => 'osc_secretary', 'label' => 'Setiausaha OSC'],
            ['id' => 'osc_steno', 'label' => 'STENO OSC'],
        ] as $role) {
            DB::table('core.roles')->updateOrInsert(
                ['id' => $role['id']],
                ['label' => $role['label'], 'account_type' => 'staff', 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        foreach ([
            ['name' => 'osc.meeting.view', 'label' => 'Akses eOSC+', 'description' => 'Membenarkan kakitangan membuka ruang mesyuarat eOSC+.'],
            ['name' => 'osc.meeting.manage', 'label' => 'Urus mesyuarat dan agenda OSC', 'description' => 'Membenarkan pengurusan mesyuarat, ahli dan agenda OSC.'],
            ['name' => 'osc.agenda.propose', 'label' => 'Cadang perkara ke agenda OSC', 'description' => 'Membenarkan pegawai jabatan mencadangkan perkara ke agenda OSC.'],
            ['name' => 'osc.agenda.endorse', 'label' => 'Sokong cadangan agenda jabatan', 'description' => 'Membenarkan ketua jabatan menyokong cadangan agenda jabatannya.'],
        ] as $permission) {
            DB::table('core.permissions')->updateOrInsert(
                ['name' => $permission['name']],
                ['label' => $permission['label'], 'description' => $permission['description'], 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $view = 'osc.meeting.view';
        $manage = 'osc.meeting.manage';
        $propose = 'osc.agenda.propose';
        $endorse = 'osc.agenda.endorse';

        $grants = [
            ['osc_secretary', null, [$view, $manage]],
            ['osc_steno', null, [$view, $manage]],
            ['planning_officer', 'JPBD', [$view, $propose]],
            ['senior_planning_officer', 'JPBD', [$view, $propose, $endorse]],
            ['building_officer', 'JBANG', [$view, $propose]],
            ['senior_building_officer', 'JBANG', [$view, $propose, $endorse]],
            ['system_admin', null, [$view, $manage, $propose, $endorse]],
        ];

        foreach ($grants as [$roleId, $departmentId, $permissions]) {
            foreach ($permissions as $permissionName) {
                DB::table('core.role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_name' => $permissionName, 'department_id' => $departmentId, 'application_type_id' => null],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }
}
