<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Officer comments per AIRS finding: attributed and append-only. The original AI finding is never changed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airs.review_finding_feedbacks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_finding_id')->constrained('airs.review_findings')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 40);
            $table->text('comment')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['review_finding_id', 'created_at']);
        });
        DB::statement("ALTER TABLE airs.review_finding_feedbacks ADD CONSTRAINT airs_feedback_status CHECK (status IN ('DITUNAIKAN','BELUM_DITUNAIKAN','TIDAK_BERKAITAN','TIDAK_DAPAT_DIKESAN','PERLU_SEMAKAN_MANUAL','PERLU_SEMAKAN_MANUAL_PEGAWAI'))");
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER airs_feedback_append_only BEFORE UPDATE OR DELETE ON airs.review_finding_feedbacks
                FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation();
            CREATE TRIGGER airs_feedback_no_truncate BEFORE TRUNCATE ON airs.review_finding_feedbacks
                FOR EACH STATEMENT EXECUTE FUNCTION core.prevent_history_mutation();
        SQL);
    }

    public function down(): void
    {
        if (DB::table('airs.review_finding_feedbacks')->exists()) {
            throw new RuntimeException('Officer feedback exists; use a data-preserving forward migration.');
        }
        Schema::dropIfExists('airs.review_finding_feedbacks');
    }
};
