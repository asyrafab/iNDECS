<?php

use Database\Seeders\EngineeringRegistrationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        (new EngineeringRegistrationSeeder)->run();
    }

    public function down(): void
    {
        if (DB::table('core.applications')->whereIn('application_type_id', ['PKT', 'PKP'])->exists()) {
            throw new RuntimeException('Engineering registrations exist; retain their workflow and permissions.');
        }
        DB::table('core.workflow_templates')->whereIn('application_type_id', ['PKT', 'PKP'])->where('version', 1)->where('registration_permission', 'kejuruteraan.application.register')->delete();
        DB::table('core.role_permissions')->whereIn('permission_name', ['kejuruteraan.application.access', 'kejuruteraan.application.register'])->delete();
        DB::table('core.permissions')->whereIn('name', ['kejuruteraan.application.access', 'kejuruteraan.application.register'])->delete();
        DB::table('core.role_permissions')->whereIn('role_id', ['engineering_officer', 'senior_engineering_officer'])->where('department_id', 'JKEJ')
            ->whereIn('application_type_id', ['PKT', 'PKP'])->where('permission_name', 'like', 'core.tasks.%')->delete();
        DB::table('core.application_types')->whereIn('id', ['PKT', 'PKP'])->update(['is_active' => false]);
    }
};
