<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.amira_guidance_turns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('context', 40);
            $table->string('request_id', 40);
            $table->text('question')->nullable();
            $table->string('heading');
            $table->text('reply');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'context', 'request_id']);
            $table->index(['user_id', 'context', 'id']);
        });
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core.prevent_guidance_turn_update() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'AMIRA guidance turns are append-only' USING ERRCODE = '42501';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER amira_guidance_turns_no_update BEFORE UPDATE ON core.amira_guidance_turns
                FOR EACH ROW EXECUTE FUNCTION core.prevent_guidance_turn_update();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('core.amira_guidance_turns');
        DB::unprepared('DROP FUNCTION IF EXISTS core.prevent_guidance_turn_update()');
    }
};
