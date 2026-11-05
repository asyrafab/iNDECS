<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ADR-363: project particulars on drafts, check keywords on catalogue items, draft uploads. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.applicant_drafts', function (Blueprint $table): void {
            $table->string('work_type', 30)->nullable();
            $table->string('building_category', 30)->nullable();
            $table->unsignedSmallInteger('storeys')->nullable();
            $table->decimal('floor_area', 12, 2)->nullable();
            $table->jsonb('site_conditions')->nullable();
        });
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->jsonb('precheck_keywords')->nullable();
        });
        Schema::create('core.applicant_draft_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('draft_id');
            $table->foreign('draft_id')->references('id')->on('core.applicant_drafts')->restrictOnDelete();
            $table->string('item_code', 40);
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('precheck_status', 12)->default('pending');
            $table->boolean('readable')->nullable();
            $table->unsignedInteger('text_chars')->nullable();
            $table->jsonb('matched_keywords')->nullable();
            $table->boolean('ocr_used')->default(false);
            $table->boolean('ocr_unavailable')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['draft_id', 'item_code', 'sequence']);
            $table->index(['draft_id', 'item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.applicant_draft_documents');
        Schema::table('document_requirements', fn (Blueprint $table) => $table->dropColumn('precheck_keywords'));
        Schema::table('core.applicant_drafts', fn (Blueprint $table) => $table->dropColumn(['work_type', 'building_category', 'storeys', 'floor_area', 'site_conditions']));
    }
};
