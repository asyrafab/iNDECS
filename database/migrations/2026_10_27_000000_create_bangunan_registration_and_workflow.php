<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.workflow_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('application_type_id', 20);
            $table->unsignedInteger('version')->default(1);
            $table->string('initial_stage', 50);
            $table->string('initial_status', 50);
            $table->string('registration_permission', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('application_type_id')->references('id')->on('application_types');
            $table->foreign('registration_permission')->references('name')->on('core.permissions');
            $table->unique(['application_type_id', 'version']);
        });

        Schema::create('core.workflow_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained('core.workflow_templates');
            $table->string('action', 100);
            $table->string('label');
            $table->string('from_stage', 50);
            $table->string('from_status', 50);
            $table->string('to_stage', 50);
            $table->string('to_status', 50);
            $table->string('permission_name', 100);
            $table->timestamps();
            $table->foreign('permission_name')->references('name')->on('core.permissions');
            $table->unique(['workflow_template_id', 'action', 'from_stage', 'from_status'], 'workflow_transition_source_unique');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('workflow_template_id')->nullable()->constrained('core.workflow_templates');
        });

        Schema::create('bangunan.app_pb_applications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('application_id')->unique();
            $table->string('building_category', 100);
            $table->string('building_use');
            $table->unsignedSmallInteger('number_of_storeys')->nullable();
            $table->unsignedInteger('number_of_units')->nullable();
            $table->decimal('gross_floor_area', 14, 2)->nullable();
            $table->text('technical_notes')->nullable();
            $table->timestamps();
            $table->foreign('application_id')->references('id')->on('applications')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('applications')->whereNotNull('workflow_template_id')->exists()) {
            throw new RuntimeException('Operational applications exist; use a reviewed data-preserving rollback.');
        }

        Schema::dropIfExists('bangunan.app_pb_applications');
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign(['workflow_template_id']);
            $table->dropColumn('workflow_template_id');
        });
        Schema::dropIfExists('core.workflow_transitions');
        Schema::dropIfExists('core.workflow_templates');
    }
};
