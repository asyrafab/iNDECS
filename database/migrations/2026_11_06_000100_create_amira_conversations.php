<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-369: AMIRA conversations are stored by iNDECS (AMIRA itself is stateless).
 * Messages cannot be edited; whole conversations are deleted after the retention period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.amira_conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->ulid('draft_id')->nullable();
            $table->foreign('draft_id')->references('id')->on('core.applicant_drafts')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'draft_id']);
            $table->index('last_message_at');
        });
        DB::statement('CREATE UNIQUE INDEX amira_conversations_user_home_unique ON core.amira_conversations (user_id) WHERE draft_id IS NULL');

        Schema::create('core.amira_messages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('conversation_id');
            $table->foreign('conversation_id')->references('id')->on('core.amira_conversations')->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('body');
            $table->jsonb('sources')->nullable();
            $table->jsonb('proposal')->nullable();
            $table->boolean('fallback')->default(false);
            $table->boolean('mentions_outcome')->default(false);
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['conversation_id', 'id']);
        });
        DB::statement("ALTER TABLE core.amira_messages ADD CONSTRAINT amira_messages_role_check CHECK (role IN ('psp', 'amira'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core.prevent_amira_message_update() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'AMIRA messages are append-only' USING ERRCODE = '42501';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER amira_messages_no_update BEFORE UPDATE ON core.amira_messages
                FOR EACH ROW EXECUTE FUNCTION core.prevent_amira_message_update();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('core.amira_messages');
        DB::unprepared('DROP FUNCTION IF EXISTS core.prevent_amira_message_update()');
        Schema::dropIfExists('core.amira_conversations');
    }
};
