<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Audited fee waiver for the paid AIRS check, granted by an authorised JKEJ officer under an approved policy.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.check_payments', function (Blueprint $table): void {
            $table->string('clearance_kind', 10)->default('paid');
            $table->foreignId('waived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('waiver_reason')->nullable();
        });
        DB::statement("ALTER TABLE core.check_payments ADD CONSTRAINT check_payment_waiver CHECK (clearance_kind IN ('paid','waived') AND (clearance_kind <> 'waived' OR (waived_by IS NOT NULL AND waiver_reason IS NOT NULL AND amount_sen = 0)))");

        $now = now();
        DB::table('core.permissions')->insertOrIgnore(['name' => 'kejuruteraan.ljup.fee_waive', 'label' => 'Pengecualian fi semakan LJUP',
            'description' => 'Mengecualikan fi semakan AIRS LJUP di bawah dasar yang diluluskan.', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('core.role_permissions')->insertOrIgnore(['role_id' => 'senior_engineering_officer', 'permission_name' => 'kejuruteraan.ljup.fee_waive',
            'department_id' => 'JKEJ', 'application_type_id' => 'LJUP', 'created_at' => $now, 'updated_at' => $now]);
        if (DB::table('core.roles')->where('id', 'system_admin')->exists()) {
            DB::table('core.role_permissions')->insertOrIgnore(['role_id' => 'system_admin', 'permission_name' => 'kejuruteraan.ljup.fee_waive',
                'department_id' => null, 'application_type_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('core.role_permissions')->where('permission_name', 'kejuruteraan.ljup.fee_waive')->delete();
        DB::table('core.permissions')->where('name', 'kejuruteraan.ljup.fee_waive')->delete();
        Schema::table('core.check_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('waived_by');
            $table->dropColumn(['clearance_kind', 'waiver_reason']);
        });
    }
};
