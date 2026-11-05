<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ADR-362: department reference library with append-only file versions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.library_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('department_id', 20);
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->string('visibility', 10);
            $table->string('status', 10)->default('active');
            $table->unsignedInteger('current_version');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('retired_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status']);
        });
        DB::statement("ALTER TABLE core.library_documents ADD CONSTRAINT library_documents_visibility_check CHECK (visibility IN ('public', 'internal'))");
        DB::statement("ALTER TABLE core.library_documents ADD CONSTRAINT library_documents_status_check CHECK (status IN ('active', 'retired'))");

        Schema::create('core.library_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('document_id');
            $table->foreign('document_id')->references('id')->on('core.library_documents')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['document_id', 'version']);
        });
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER library_document_versions_append_only_guard
                BEFORE UPDATE OR DELETE ON core.library_document_versions
                FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation();
        SQL);

        Schema::create('core.library_document_application_types', function (Blueprint $table): void {
            $table->ulid('document_id');
            $table->foreign('document_id')->references('id')->on('core.library_documents')->cascadeOnDelete();
            $table->string('application_type_id', 20);
            $table->foreign('application_type_id')->references('id')->on('application_types')->restrictOnDelete();
            $table->primary(['document_id', 'application_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.library_document_application_types');
        Schema::dropIfExists('core.library_document_versions');
        Schema::dropIfExists('core.library_documents');
    }
};
