<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.application_recommendations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('applications')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('outcome', 40);
            $table->text('reasons');
            $table->text('conditions')->nullable();
            $table->text('evidence_references');
            $table->text('missing_document_explanation')->nullable();
            $table->jsonb('source_snapshot');
            $table->char('source_hash', 64);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['application_id', 'revision']);
        });
        Schema::create('core.application_letter_drafts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('recommendation_id')->unique()->constrained('core.application_recommendations')->restrictOnDelete();
            $table->string('template_key', 100);
            $table->unsignedInteger('template_version');
            $table->jsonb('content_snapshot');
            $table->char('content_hash', 64);
            $table->timestampTz('created_at')->useCurrent();
        });
        foreach (['application_recommendations', 'application_letter_drafts'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON core.{$table} FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation()");
            DB::unprepared("CREATE TRIGGER {$table}_no_truncate BEFORE TRUNCATE ON core.{$table} FOR EACH STATEMENT EXECUTE FUNCTION core.prevent_history_mutation()");
        }
    }

    public function down(): void
    {
        if (DB::table('core.application_recommendations')->exists() || DB::table('core.application_letter_drafts')->exists()) {
            throw new RuntimeException('Internal recommendation records exist; use a data-preserving rollback.');
        }
        Schema::dropIfExists('core.application_letter_drafts');
        Schema::dropIfExists('core.application_recommendations');
    }
};
