<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AIRS now runs in-process (app/Airs). The HTTP connector's request/feedback
// tables are retired; core.accepted_submissions stays as the acceptance ledger.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('integration.airs_feedback');
        Schema::dropIfExists('integration.airs_reviews');
    }

    public function down(): void
    {
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
};
