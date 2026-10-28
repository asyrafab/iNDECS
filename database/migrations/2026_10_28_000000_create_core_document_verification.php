<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.workflow_templates', function (Blueprint $table): void {
            $table->string('document_upload_permission', 100)->nullable();
            $table->string('document_review_permission', 100)->nullable();
        });
        Schema::table('core.workflow_transitions', function (Blueprint $table): void {
            $table->string('guard', 100)->nullable();
        });
        Schema::table('application_documents', function (Blueprint $table): void {
            $table->string('scan_status', 30)->default('pending');
            $table->timestamp('scanned_at')->nullable();
        });
        Schema::create('core.document_catalogues', function (Blueprint $table): void {
            $table->string('application_type_id', 20)->primary();
            $table->unsignedInteger('version');
            $table->string('source_reference', 500);
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->foreign('application_type_id')->references('id')->on('application_types')->restrictOnDelete();
        });
        Schema::create('core.document_checklists', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('application_id')->unique();
            $table->unsignedInteger('catalogue_version');
            $table->string('source_reference', 500);
            $table->string('status', 30)->default('open');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamps();
            $table->foreign('application_id')->references('id')->on('applications')->restrictOnDelete();
        });
        Schema::create('core.document_checklist_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('checklist_id');
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('document_name');
            $table->string('allowed_extensions');
            $table->unsignedInteger('max_file_size_kb');
            $table->boolean('is_required');
            $table->unsignedSmallInteger('sort_order');
            $table->string('status', 30)->default('pending');
            $table->ulid('document_id')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->foreign('checklist_id')->references('id')->on('core.document_checklists')->restrictOnDelete();
            $table->foreign('document_id')->references('id')->on('application_documents')->restrictOnDelete();
            $table->unique(['checklist_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('core.document_checklists')->exists() || DB::table('application_documents')->where('scan_status', 'clean')->exists()) {
            throw new RuntimeException('Document evidence exists; use a data-preserving forward fix.');
        }
        // Remove guarded actions before dropping their guard column; an older binary
        // must never inherit an unguarded route to fee assessment after rollback.
        $templates = DB::table('core.workflow_templates')->whereIn('application_type_id', ['PB', 'PBK', 'PBS', 'PMT', 'TEL'])->where('version', 1)->pluck('id');
        DB::table('core.workflow_transitions')->whereIn('workflow_template_id', $templates)
            ->whereIn('action', ['complete_document_verification', 'return_document_verification', 'resubmit_documents'])->delete();
        Schema::dropIfExists('core.document_checklist_items');
        Schema::dropIfExists('core.document_checklists');
        Schema::dropIfExists('core.document_catalogues');
        Schema::table('application_documents', fn (Blueprint $table) => $table->dropColumn(['scan_status', 'scanned_at']));
        Schema::table('core.workflow_transitions', fn (Blueprint $table) => $table->dropColumn('guard'));
        Schema::table('core.workflow_templates', fn (Blueprint $table) => $table->dropColumn(['document_upload_permission', 'document_review_permission']));
    }
};
