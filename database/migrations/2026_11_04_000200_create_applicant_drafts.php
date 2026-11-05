<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.applicant_drafts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('application_type_id', 20);
            $table->foreign('application_type_id')->references('id')->on('application_types')->restrictOnDelete();
            $table->string('title', 500);
            $table->text('site_address')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('core.applicant_draft_revisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('draft_id');
            $table->foreign('draft_id')->references('id')->on('core.applicant_drafts')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('snapshot');
            $table->timestamp('created_at');
            $table->unique(['draft_id', 'version']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER applicant_draft_revisions_append_only_guard
                BEFORE UPDATE OR DELETE ON core.applicant_draft_revisions
                FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('core.applicant_draft_revisions');
        Schema::dropIfExists('core.applicant_drafts');
    }
};
