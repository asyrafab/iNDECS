<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// LJUP (Pelan Lampu Jalan Untuk Pembangunan) under Jabatan Kejuruteraan, its officer roles and
// permissions, and the lighting types an LJUP draft declares. No checklist content is seeded:
// JKEJ publishes it (php artisan catalogue:publish LJUP <file>).
return new class extends Migration
{
    private const PERMISSIONS = [
        'kejuruteraan.ljup.access' => ['Akses permohonan LJUP', 'Membuka permohonan Pelan Lampu Jalan Untuk Pembangunan.'],
        'kejuruteraan.ljup.review' => ['Semakan teknikal LJUP', 'Menyemak dapatan AIRS dan membuat ulasan teknikal LJUP.'],
        'kejuruteraan.ljup.documents.configure' => ['Katalog dokumen LJUP', 'Menerbitkan senarai semak dokumen LJUP.'],
    ];

    private const GRANTS = [
        'engineering_officer' => ['kejuruteraan.ljup.access', 'kejuruteraan.ljup.review'],
        'senior_engineering_officer' => ['kejuruteraan.ljup.access', 'kejuruteraan.ljup.review', 'kejuruteraan.ljup.documents.configure'],
    ];

    public function up(): void
    {
        $now = now();
        DB::table('departments')->insertOrIgnore(['id' => 'JKEJ', 'name' => 'Jabatan Kejuruteraan', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('application_types')->insertOrIgnore(['id' => 'LJUP', 'name' => 'Pelan Lampu Jalan Untuk Pembangunan',
            'primary_department_id' => 'JKEJ', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach (['engineering_officer' => 'Pegawai Kejuruteraan', 'senior_engineering_officer' => 'Pegawai Kanan Kejuruteraan'] as $id => $label) {
            DB::table('core.roles')->insertOrIgnore(['id' => $id, 'label' => $label, 'account_type' => 'staff', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (self::PERMISSIONS as $name => [$label, $description]) {
            DB::table('core.permissions')->insertOrIgnore(['name' => $name, 'label' => $label, 'description' => $description, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (self::GRANTS as $role => $permissions) {
            foreach ($permissions as $permission) {
                DB::table('core.role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_name' => $permission,
                    'department_id' => 'JKEJ', 'application_type_id' => 'LJUP', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        // System administrators hold every permission unscoped (ADR-307 superadmin rule).
        if (DB::table('core.roles')->where('id', 'system_admin')->exists()) {
            foreach (array_keys(self::PERMISSIONS) as $permission) {
                DB::table('core.role_permissions')->insertOrIgnore(['role_id' => 'system_admin', 'permission_name' => $permission,
                    'department_id' => null, 'application_type_id' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        Schema::table('core.applicant_drafts', function (Blueprint $table): void {
            $table->jsonb('lighting_types')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('core.applicant_drafts', fn (Blueprint $table) => $table->dropColumn('lighting_types'));
        DB::table('core.role_permissions')->whereIn('permission_name', array_keys(self::PERMISSIONS))->delete();
        DB::table('core.permissions')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        DB::table('core.roles')->whereIn('id', array_keys(self::GRANTS))->delete();
        DB::table('application_types')->where('id', 'LJUP')->delete();
    }
};
