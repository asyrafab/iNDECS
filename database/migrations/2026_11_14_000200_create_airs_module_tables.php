<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// AIRS review engine tables (ported from the archived Plan Review repo, started clean).
// One review case per application; each check is one version (check_number 1 or 2).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS airs');

        Schema::create('airs.review_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->unique()->constrained('applications')->restrictOnDelete();
            $table->string('review_module_code', 80)->index();
            $table->jsonb('selected_lighting_types')->nullable();
            $table->unsignedSmallInteger('document_set_version')->default(1);
            $table->string('clearance_kind', 10)->nullable();
            $table->string('payment_clearance_reference')->nullable();
            $table->timestampTz('payment_cleared_at')->nullable();
            $table->string('status');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('current_step')->nullable();
            $table->unsignedBigInteger('total_original_bytes')->default(0);
            $table->unsignedBigInteger('total_extracted_bytes')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedInteger('page_count')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('warnings')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE airs.review_cases ADD CONSTRAINT airs_case_clearance CHECK (clearance_kind IS NULL OR (clearance_kind IN ('paid','waived') AND length(trim(payment_clearance_reference)) > 0 AND payment_cleared_at IS NOT NULL))");

        Schema::create('airs.review_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_case_id')->constrained('airs.review_cases')->cascadeOnDelete();
            $table->unsignedSmallInteger('check_number');
            $table->char('document_set_hash', 64)->nullable();
            $table->string('application_version', 40)->nullable();
            $table->string('rule_pack');
            $table->string('rule_version');
            $table->string('prompt_version');
            $table->string('report_version');
            $table->string('ai_provider');
            $table->string('text_model')->nullable();
            $table->string('vision_model')->nullable();
            $table->string('status')->default('UPLOADED');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('overall_state')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('shadow_outcome', 20)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['review_case_id', 'check_number']);
        });
        DB::statement('ALTER TABLE airs.review_versions ADD CONSTRAINT airs_version_check_number CHECK (check_number IN (1, 2))');
        DB::statement("ALTER TABLE airs.review_versions ADD CONSTRAINT airs_version_shadow CHECK (shadow_outcome IS NULL OR shadow_outcome IN ('MEETS_THRESHOLD','BELOW_THRESHOLD'))");

        Schema::create('airs.review_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_case_id')->constrained('airs.review_cases')->cascadeOnDelete();
            $table->foreignUlid('source_document_id')->nullable()->constrained('application_documents')->restrictOnDelete();
            $table->ulid('parent_archive_file_id')->nullable();
            $table->unsignedSmallInteger('submission_number')->default(1)->index();
            $table->string('original_name');
            $table->text('relative_path')->nullable();
            $table->text('safe_storage_path');
            $table->string('mime_type');
            $table->string('extension', 12);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('page_count')->nullable();
            $table->decimal('page_width_max', 10, 2)->nullable();
            $table->decimal('page_height_max', 10, 2)->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_duplicate')->default(false);
            $table->ulid('duplicate_of_file_id')->nullable();
            $table->string('processing_status')->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();
            $table->index(['review_case_id', 'sha256']);
        });
        Schema::table('airs.review_files', function (Blueprint $table): void {
            $table->foreign('parent_archive_file_id')->references('id')->on('airs.review_files')->nullOnDelete();
            $table->foreign('duplicate_of_file_id')->references('id')->on('airs.review_files')->nullOnDelete();
        });

        Schema::create('airs.review_pages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_file_id')->constrained('airs.review_files')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('width_points', 10, 2)->nullable();
            $table->decimal('height_points', 10, 2)->nullable();
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->string('content_type')->default('UNKNOWN');
            $table->longText('embedded_text')->nullable();
            $table->unsignedInteger('text_length')->default(0);
            $table->longText('ocr_text')->nullable();
            $table->decimal('ocr_confidence', 5, 4)->nullable();
            $table->text('thumbnail_path')->nullable();
            $table->text('preview_path')->nullable();
            $table->text('high_res_path')->nullable();
            $table->char('page_hash', 64)->nullable();
            $table->jsonb('processing_metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['review_file_id', 'page_number']);
        });

        Schema::create('airs.page_regions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_page_id')->constrained('airs.review_pages')->cascadeOnDelete();
            $table->string('region_type');
            $table->string('label')->nullable();
            $table->jsonb('bbox_normalised');
            $table->text('image_path');
            $table->longText('text')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestampsTz();
        });

        Schema::create('airs.ai_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->constrained('airs.review_versions')->cascadeOnDelete();
            $table->string('task_type');
            $table->string('rule_id')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->string('provider_request_id')->nullable();
            $table->string('prompt_id');
            $table->string('prompt_version');
            $table->string('schema_id');
            $table->char('request_hash', 64);
            $table->jsonb('input_manifest');
            $table->text('raw_response_path')->nullable();
            $table->jsonb('parsed_response')->nullable();
            $table->jsonb('usage_json')->nullable();
            $table->jsonb('coverage_json')->nullable();
            $table->string('status');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('airs.document_classifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->constrained('airs.review_versions')->cascadeOnDelete();
            $table->foreignUlid('review_file_id')->constrained('airs.review_files')->cascadeOnDelete();
            $table->string('primary_type');
            $table->jsonb('secondary_tags');
            $table->decimal('confidence', 5, 4);
            $table->text('reason');
            $table->jsonb('evidence_page_numbers');
            $table->jsonb('warnings');
            $table->foreignUlid('ai_run_id')->nullable()->constrained('airs.ai_runs')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['review_version_id', 'review_file_id']);
        });

        Schema::create('airs.application_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->unique()->constrained('airs.review_versions')->cascadeOnDelete();
            $table->jsonb('profile_json');
            $table->jsonb('confidence_summary')->nullable();
            $table->jsonb('conflicts_json');
            $table->timestampsTz();
        });

        Schema::create('airs.review_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('rule_pack');
            $table->string('rule_version');
            $table->string('rule_id');
            $table->string('title');
            $table->string('section');
            $table->string('source_document');
            $table->unsignedInteger('source_page')->nullable();
            $table->string('source_section')->nullable();
            $table->string('stage');
            $table->string('category', 40)->nullable();
            $table->jsonb('applicability_tags')->nullable();
            $table->longText('requirement');
            $table->jsonb('applies_when');
            $table->string('evaluation_method');
            $table->jsonb('required_evidence_types');
            $table->boolean('evidence_required')->default(true);
            $table->string('severity');
            $table->boolean('manual_confirmation_required')->default(false);
            $table->text('standard_comment')->nullable();
            $table->boolean('active')->default(true);
            $table->string('lifecycle_status', 30)->nullable();
            $table->date('effective_from')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->text('change_reason')->nullable();
            $table->char('rule_hash', 64);
            $table->timestampsTz();
            $table->unique(['rule_pack', 'rule_version', 'rule_id']);
        });

        Schema::create('airs.review_findings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->constrained('airs.review_versions')->cascadeOnDelete();
            $table->foreignUlid('review_rule_id')->constrained('airs.review_rules')->restrictOnDelete();
            $table->string('rule_id');
            $table->boolean('applicable')->nullable();
            $table->string('status');
            $table->decimal('confidence', 5, 4)->default(0);
            $table->longText('finding');
            $table->jsonb('extracted_values');
            $table->text('manual_review_reason')->nullable();
            $table->jsonb('warnings');
            $table->string('evaluation_method');
            $table->foreignUlid('ai_run_id')->nullable()->constrained('airs.ai_runs')->nullOnDelete();
            $table->boolean('is_superseded')->default(false);
            $table->timestampsTz();
            $table->index(['review_version_id', 'rule_id', 'is_superseded']);
        });

        Schema::create('airs.review_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_finding_id')->constrained('airs.review_findings')->cascadeOnDelete();
            $table->foreignUlid('review_file_id')->constrained('airs.review_files')->cascadeOnDelete();
            $table->foreignUlid('review_page_id')->nullable()->constrained('airs.review_pages')->nullOnDelete();
            $table->foreignUlid('page_region_id')->nullable()->constrained('airs.page_regions')->nullOnDelete();
            $table->unsignedInteger('page_number')->nullable();
            $table->string('region_label')->nullable();
            $table->jsonb('bbox_normalised')->nullable();
            $table->longText('quote_or_value');
            $table->string('evidence_type');
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestampsTz();
        });

        Schema::create('airs.processing_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->constrained('airs.review_versions')->cascadeOnDelete();
            $table->string('task_name');
            $table->string('scope_key')->nullable();
            $table->string('status');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('airs.generated_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->constrained('airs.review_versions')->cascadeOnDelete();
            $table->string('report_version');
            $table->string('language')->default('ms');
            $table->jsonb('report_json');
            $table->text('html_path')->nullable();
            $table->text('pdf_path')->nullable();
            $table->char('findings_hash', 64);
            $table->timestampsTz();
        });

        Schema::create('airs.system_config_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->unique()->constrained('airs.review_versions')->cascadeOnDelete();
            $table->jsonb('config_json');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        foreach (['system_config_snapshots', 'generated_reports', 'processing_tasks', 'review_evidence', 'review_findings',
            'review_rules', 'application_profiles', 'document_classifications', 'ai_runs', 'page_regions', 'review_pages',
            'review_files', 'review_versions', 'review_cases'] as $table) {
            Schema::dropIfExists('airs.'.$table);
        }
    }
};
