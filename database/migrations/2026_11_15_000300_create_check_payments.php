<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fee payments for the paid AIRS check (one fee covers up to two checks per application).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.check_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('draft_id');
            $table->foreign('draft_id')->references('id')->on('core.applicant_drafts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('amount_sen');
            $table->string('reference', 60)->unique();
            $table->string('status', 12)->default('pending');
            $table->string('gateway', 30);
            $table->string('gateway_event_id', 120)->nullable()->unique();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();
            $table->index(['draft_id', 'status']);
        });
        DB::statement("ALTER TABLE core.check_payments ADD CONSTRAINT check_payment_status CHECK (status IN ('pending','paid','cancelled') AND (status <> 'paid' OR (paid_at IS NOT NULL AND gateway_event_id IS NOT NULL)))");
        DB::statement("CREATE UNIQUE INDEX check_payments_one_paid_per_draft ON core.check_payments (draft_id) WHERE status = 'paid'");
    }

    public function down(): void
    {
        Schema::dropIfExists('core.check_payments');
    }
};
