<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// One paid AIRS check journey per draft: payment -> check 1 -> optional amendment + check 2 -> handover.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.paid_check_journeys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('draft_id')->unique();
            $table->foreign('draft_id')->references('id')->on('core.applicant_drafts')->restrictOnDelete();
            $table->foreignUlid('application_id')->unique()->constrained('applications')->restrictOnDelete();
            $table->ulid('payment_id')->unique();
            $table->foreign('payment_id')->references('id')->on('core.check_payments')->restrictOnDelete();
            $table->string('state', 30);
            $table->jsonb('lighting_types');
            $table->timestampTz('amendment_due_at')->nullable();
            $table->timestampTz('extended_at')->nullable();
            $table->timestampTz('amended_at')->nullable();
            $table->unsignedSmallInteger('final_check_number')->nullable();
            $table->timestampTz('handed_over_at')->nullable();
            $table->timestampsTz();
            $table->index(['state', 'amendment_due_at']);
        });
        DB::statement("ALTER TABLE core.paid_check_journeys ADD CONSTRAINT paid_check_state CHECK (state IN ('CHECK_1_RUNNING','AWAITING_APPLICANT','CHECK_2_RUNNING','HANDED_OVER') AND (final_check_number IS NULL OR final_check_number IN (1,2)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('core.paid_check_journeys');
    }
};
