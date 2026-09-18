<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->string('id', 20)->primary();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('department_id', 20)->nullable()->after('id');
            $table->string('role', 50)->default('planning_officer')->after('department_id');
            $table->boolean('is_active')->default(true)->after('role');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('application_types', function (Blueprint $table): void {
            $table->string('id', 20)->primary();
            $table->string('name');
            $table->string('primary_department_id', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('primary_department_id')->references('id')->on('departments');
        });

        Schema::create('development_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('overall_status', 50)->default('active');
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('development_case_id')->nullable();
            $table->string('application_type_id', 20);
            $table->string('application_reference')->unique();
            $table->string('external_reference')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('submission_date');
            $table->unsignedSmallInteger('submission_year');
            $table->string('responsible_department_id', 20);
            $table->string('current_stage', 50);
            $table->string('current_status', 50);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal');
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();

            $table->foreign('development_case_id')->references('id')->on('development_cases')->nullOnDelete();
            $table->foreign('application_type_id')->references('id')->on('application_types');
            $table->foreign('responsible_department_id')->references('id')->on('departments');
            $table->index(['application_type_id', 'current_status']);
            $table->index(['responsible_department_id', 'current_stage']);
            $table->index(['submission_year', 'submission_date']);
        });

        Schema::create('parties', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('party_type', 30)->default('organisation');
            $table->string('name');
            $table->string('registration_number')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('application_parties', function (Blueprint $table): void {
            $table->id();
            $table->ulid('application_id');
            $table->ulid('party_id');
            $table->string('role', 50);
            $table->boolean('is_primary')->default(false);
            $table->json('submitted_snapshot');
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->restrictOnDelete();
            $table->unique(['application_id', 'party_id', 'role']);
            $table->index(['application_id', 'role']);
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->text('development_address');
            $table->string('district', 100);
            $table->string('mukim', 100);
            $table->string('state', 100)->default('Pulau Pinang');
            $table->decimal('site_area', 14, 4)->nullable();
            $table->string('site_area_unit', 20)->default('hectare');
            $table->string('arcgis_global_id', 50)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('application_sites', function (Blueprint $table): void {
            $table->id();
            $table->ulid('application_id');
            $table->ulid('site_id');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->restrictOnDelete();
            $table->unique(['application_id', 'site_id']);
        });

        Schema::create('lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('lot_number', 100)->index();
            $table->string('lot_type', 50)->nullable();
            $table->string('section', 100)->nullable();
            $table->string('mukim', 100);
            $table->string('district', 100);
            $table->string('state', 100)->default('Pulau Pinang');
            $table->decimal('land_area', 14, 4)->nullable();
            $table->string('land_area_unit', 20)->default('hectare');
            $table->timestamps();
        });

        Schema::create('application_lots', function (Blueprint $table): void {
            $table->id();
            $table->ulid('application_id');
            $table->ulid('lot_id');
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            $table->foreign('lot_id')->references('id')->on('lots')->restrictOnDelete();
            $table->unique(['application_id', 'lot_id']);
        });

        Schema::create('reference_sequences', function (Blueprint $table): void {
            $table->string('application_type_id', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->primary(['application_type_id', 'year']);
            $table->foreign('application_type_id')->references('id')->on('application_types');
        });

        Schema::create('stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('application_id');
            $table->string('previous_stage', 50)->nullable();
            $table->string('new_stage', 50);
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->string('action', 100);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            $table->index(['application_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('auditable_type', 100);
            $table->string('auditable_id', 50);
            $table->string('event', 100);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stage_histories');
        Schema::dropIfExists('reference_sequences');
        Schema::dropIfExists('application_lots');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('application_sites');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('application_parties');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('development_cases');
        Schema::dropIfExists('application_types');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'role', 'is_active']);
        });

        Schema::dropIfExists('departments');
    }
};
