<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS core');

        Schema::create('core.roles', function (Blueprint $table): void {
            $table->string('id', 50)->primary();
            $table->string('label');
            $table->string('account_type', 20)->default('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('core.permissions', function (Blueprint $table): void {
            $table->string('name', 100)->primary();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('core.role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('role_id', 50);
            $table->string('permission_name', 100);
            $table->string('department_id', 20)->nullable();
            $table->string('application_type_id', 20)->nullable();
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('core.roles')->cascadeOnDelete();
            $table->foreign('permission_name')->references('name')->on('core.permissions')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->foreign('application_type_id')->references('id')->on('application_types')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_name', 'department_id', 'application_type_id'], 'core_role_permissions_unique_scope');
            $table->index(['permission_name', 'department_id', 'application_type_id'], 'core_role_permissions_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.role_permissions');
        Schema::dropIfExists('core.permissions');
        Schema::dropIfExists('core.roles');
    }
};
