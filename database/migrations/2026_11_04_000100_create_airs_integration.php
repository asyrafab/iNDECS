<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS integration');
        Schema::create('core.accepted_submissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('applications')->restrictOnDelete();
            $table->jsonb('document_manifest');
            $table->char('document_set_hash', 64);
            $table->string('clearance_kind', 10);
            $table->string('payment_clearance_reference')->unique();
            $table->timestampTz('payment_cleared_at');
            $table->timestampTz('accepted_at')->index();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE core.accepted_submissions ADD CONSTRAINT accepted_submission_clearance CHECK (clearance_kind IN ('paid','waived') AND length(trim(payment_clearance_reference)) > 0 AND payment_cleared_at <= accepted_at AND (clearance_kind <> 'waived' OR cleared_by IS NOT NULL))");
        DB::unprepared('CREATE TRIGGER accepted_submissions_immutable BEFORE UPDATE OR DELETE ON core.accepted_submissions FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation()');
        DB::unprepared('CREATE TRIGGER accepted_submissions_no_truncate BEFORE TRUNCATE ON core.accepted_submissions FOR EACH STATEMENT EXECUTE FUNCTION core.prevent_history_mutation()');
        Schema::create('integration.airs_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignUlid('submission_id')->constrained('core.accepted_submissions')->restrictOnDelete();
            $table->string('module_code', 40);
            $table->jsonb('lighting_types')->default('[]');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('state', 20)->default('queued')->index();
            $table->ulid('remote_id')->nullable()->unique();
            $table->jsonb('result')->nullable();
            $table->string('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['submission_id', 'module_code']);
        });
        Schema::create('integration.airs_feedback', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->constrained('integration.airs_reviews')->restrictOnDelete();
            $table->ulid('finding_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 50);
            $table->text('comment')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        if (DB::table('core.accepted_submissions')->exists() || DB::table('integration.airs_reviews')->exists()) {
            throw new RuntimeException('Integration evidence exists; use a data-preserving forward migration.');
        }
        Schema::dropIfExists('integration.airs_feedback');
        Schema::dropIfExists('integration.airs_reviews');
        Schema::dropIfExists('core.accepted_submissions');
    }
};
