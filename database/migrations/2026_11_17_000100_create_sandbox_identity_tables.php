<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.external_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->string('provider', 30)->default('mydigitalid');
            $table->string('environment', 20)->default('sandbox');
            $table->string('issuer', 255);
            $table->string('subject', 255);
            $table->text('official_name'); // encrypted, separate from display name
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['provider', 'issuer', 'subject'], 'identity_provider_subject_unique');
            $table->unique(['user_id', 'provider', 'environment'], 'identity_user_environment_unique');
        });
        Schema::create('core.identity_login_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->text('destination'); // encrypted
            $table->char('lookup', 64)->unique();
            $table->string('key_version', 10);
            $table->boolean('active')->default(true);
            $table->timestamp('verified_at');
        });
        Schema::create('core.identity_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('purpose', 30);
            $table->char('browser', 64);
            $table->char('lookup', 64);
            $table->char('digest', 64);
            $table->string('key_version', 10);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
        });
        DB::statement('CREATE UNIQUE INDEX identity_one_active_challenge ON core.identity_challenges (user_id, purpose) WHERE active AND user_id IS NOT NULL');
        Schema::create('core.identity_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('intent', 10);
            $table->string('stage', 20);
            $table->char('browser', 64);
            $table->text('payload'); // encrypted state, nonce, PKCE and pending proof
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        foreach (['identity_transactions', 'identity_challenges', 'identity_login_contacts', 'external_identities'] as $table) {
            Schema::dropIfExists('core.'.$table);
        }
    }
};
