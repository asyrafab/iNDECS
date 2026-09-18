<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->date('due_date')->nullable()->after('assigned_at');
        });

        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('allowed_extensions')->default('pdf,jpg,jpeg,png');
            $table->unsignedInteger('max_file_size_kb')->default(20480);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('application_type_id', 20);
            $table->string('permission_category', 100)->nullable();
            $table->string('permission_type', 100)->nullable();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('application_type_id')->references('id')->on('application_types')->cascadeOnDelete();
            $table->index(['application_type_id', 'permission_category', 'permission_type']);
        });

        Schema::create('application_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('application_id');
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->string('verification_status', 30)->default('pending');
            $table->text('verification_remarks')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->ulid('supersedes_id')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            $table->foreign('supersedes_id')->references('id')->on('application_documents')->nullOnDelete();
            $table->unique(['application_id', 'document_type_id', 'revision_number'], 'application_document_revision_unique');
            $table->index(['application_id', 'document_type_id', 'is_current'], 'application_document_current_index');
        });

        Schema::create('pkm_completeness_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('application_id');
            $table->unsignedSmallInteger('cycle_number');
            $table->string('status', 30)->default('in_progress');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_remarks')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();
            $table->unique(['application_id', 'cycle_number']);
        });

        Schema::create('pkm_completeness_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('review_id');
            $table->foreignId('document_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->string('requirement_name');
            $table->boolean('is_required')->default(true);
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('pkm_completeness_reviews')->cascadeOnDelete();
            $table->unique(['review_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkm_completeness_items');
        Schema::dropIfExists('pkm_completeness_reviews');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('document_requirements');
        Schema::dropIfExists('document_types');

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['assigned_at', 'due_date']);
        });
    }
};
