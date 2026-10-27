<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS core');

        Schema::table('stage_histories', function (Blueprint $table): void {
            $table->dropForeign(['application_id']);
            $table->dropForeign(['actor_id']);

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->restrictOnDelete();

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_id']);

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core.prevent_history_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Audit and stage history records are append-only and cannot be modified or deleted.'
                    USING ERRCODE = '42501';
            END;
            $$;

            DROP TRIGGER IF EXISTS audit_logs_append_only_guard ON audit_logs;
            CREATE TRIGGER audit_logs_append_only_guard
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION core.prevent_history_mutation();

            DROP TRIGGER IF EXISTS stage_histories_append_only_guard ON stage_histories;
            CREATE TRIGGER stage_histories_append_only_guard
                BEFORE UPDATE OR DELETE ON stage_histories
                FOR EACH ROW
                EXECUTE FUNCTION core.prevent_history_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_append_only_guard ON audit_logs;
            DROP TRIGGER IF EXISTS stage_histories_append_only_guard ON stage_histories;
            DROP FUNCTION IF EXISTS core.prevent_history_mutation();
        SQL);

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_id']);

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('stage_histories', function (Blueprint $table): void {
            $table->dropForeign(['application_id']);
            $table->dropForeign(['actor_id']);

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
