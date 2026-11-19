<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.officer_tasks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('core.applications')->restrictOnDelete();
            $table->string('department_id', 20);
            $table->foreign('department_id')->references('id')->on('core.departments')->restrictOnDelete();
            $table->string('title', 255);
            $table->text('instructions')->nullable();
            $table->foreignId('assignee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('revision')->default(1);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['assignee_id', 'status', 'due_date']);
            $table->index(['department_id', 'status']);
        });
        DB::statement("ALTER TABLE core.officer_tasks ADD CONSTRAINT officer_tasks_status_check CHECK (status IN ('open', 'in_progress', 'completed', 'cancelled'))");
        Schema::create('core.officer_task_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained('core.officer_tasks')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->jsonb('before_values');
            $table->jsonb('after_values');
            $table->text('note')->nullable();
            $table->jsonb('evidence');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['task_id', 'revision']);
        });
        DB::unprepared('CREATE TRIGGER officer_task_actions_immutable BEFORE UPDATE OR DELETE ON core.officer_task_actions FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation()');
        DB::unprepared('CREATE TRIGGER officer_task_actions_no_truncate BEFORE TRUNCATE ON core.officer_task_actions FOR EACH STATEMENT EXECUTE FUNCTION core.prevent_history_mutation()');
        Schema::create('core.officer_task_evidence', function (Blueprint $table): void {
            $table->foreignUlid('action_id')->constrained('core.officer_task_actions')->restrictOnDelete();
            $table->foreignUlid('document_id')->constrained('core.application_documents')->restrictOnDelete();
            $table->primary(['action_id', 'document_id']);
        });
        DB::unprepared('CREATE TRIGGER officer_task_evidence_immutable BEFORE UPDATE OR DELETE ON core.officer_task_evidence FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation()');
        DB::unprepared('CREATE TRIGGER officer_task_evidence_no_truncate BEFORE TRUNCATE ON core.officer_task_evidence FOR EACH STATEMENT EXECUTE FUNCTION core.prevent_history_mutation()');
    }

    public function down(): void
    {
        if (DB::table('core.officer_tasks')->exists()) {
            throw new RuntimeException('Officer tasks exist; use a data-preserving recovery.');
        }
        Schema::dropIfExists('core.officer_task_evidence');
        Schema::dropIfExists('core.officer_task_actions');
        Schema::dropIfExists('core.officer_tasks');
    }
};
