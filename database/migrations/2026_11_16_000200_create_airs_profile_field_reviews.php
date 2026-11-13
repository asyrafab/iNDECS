<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Officer dispositions on MATRIX-extracted profile fields: attributed, revisioned and append-only.
// They never change official application particulars.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airs.profile_field_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_version_id')->constrained('airs.review_versions')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('field', 60);
            $table->string('disposition', 12);
            $table->text('value')->nullable();
            $table->text('reason');
            $table->unsignedInteger('revision');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['review_version_id', 'field', 'revision']);
        });
        DB::statement("ALTER TABLE airs.profile_field_reviews ADD CONSTRAINT airs_field_review_disposition CHECK (disposition IN ('ACCEPTED','CORRECTED','REJECTED'))");
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER airs_field_reviews_append_only BEFORE UPDATE OR DELETE ON airs.profile_field_reviews
                FOR EACH ROW EXECUTE FUNCTION core.prevent_history_mutation();
            CREATE TRIGGER airs_field_reviews_no_truncate BEFORE TRUNCATE ON airs.profile_field_reviews
                FOR EACH STATEMENT EXECUTE FUNCTION core.prevent_history_mutation();
        SQL);
    }

    public function down(): void
    {
        if (DB::table('airs.profile_field_reviews')->exists()) {
            throw new RuntimeException('Field reviews exist; use a data-preserving forward migration.');
        }
        Schema::dropIfExists('airs.profile_field_reviews');
    }
};
