<?php

use Database\Seeders\LibrarySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        (new LibrarySeeder)->run();
    }

    public function down(): void
    {
        DB::table('core.role_permissions')->whereIn('permission_name', ['library.manage', 'library.view'])->delete();
        DB::table('core.permissions')->whereIn('name', ['library.manage', 'library.view'])->delete();
        DB::table('core.roles')->where('id', 'penolong_pengarah')->delete();
        if (DB::table('core.roles')->where('id', 'senior_building_officer')->exists()
            && DB::table('core.permissions')->where('name', 'bangunan.application.documents.configure')->exists()
            && DB::table('departments')->where('id', 'JBANG')->exists()) {
            DB::table('core.role_permissions')->insertOrIgnore(['role_id' => 'senior_building_officer',
                'permission_name' => 'bangunan.application.documents.configure', 'department_id' => 'JBANG',
                'application_type_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};
