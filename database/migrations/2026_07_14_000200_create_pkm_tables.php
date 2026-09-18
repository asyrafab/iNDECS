<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_pkm_applications', function (Blueprint $table): void {
            $table->ulid('application_id')->primary();
            $table->string('permission_category', 100);
            $table->string('permission_type', 100);
            $table->string('existing_land_use');
            $table->string('proposed_land_use');
            $table->decimal('density', 12, 2)->nullable();
            $table->decimal('plot_ratio', 8, 3)->nullable();
            $table->unsignedInteger('number_of_units')->nullable();
            $table->unsignedSmallInteger('number_of_storeys')->nullable();
            $table->decimal('building_height', 10, 2)->nullable();
            $table->text('planning_standards')->nullable();
            $table->text('technical_conditions')->nullable();
            $table->date('approval_valid_until')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
        });

        Schema::create('app_pkm_components', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('application_id');
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->unsignedInteger('number_of_units')->nullable();
            $table->decimal('floor_area', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('application_id')->on('app_pkm_applications')->cascadeOnDelete();
            $table->index(['application_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_pkm_components');
        Schema::dropIfExists('app_pkm_applications');
    }
};
