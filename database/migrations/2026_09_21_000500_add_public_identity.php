<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type', 20)->default('staff')->index();
            $table->boolean('has_password')->default(true);
            $table->string('google_subject')->nullable()->unique();
        });
        DB::statement('CREATE SCHEMA IF NOT EXISTS core');
        Schema::create('core.public_access_tokens', function (Blueprint $table): void {
            $table->char('token_hash', 64)->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 20);
            $table->timestamp('expires_at');
            $table->unique(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core.public_access_tokens');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['google_subject']);
            $table->dropIndex(['account_type']);
            $table->dropColumn(['account_type', 'has_password', 'google_subject']);
        });
    }
};
