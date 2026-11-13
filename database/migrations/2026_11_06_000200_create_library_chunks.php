<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ADR-369: searchable text of library document versions, so AMIRA can ground and cite answers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core.library_chunks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('version_id');
            $table->foreign('version_id')->references('id')->on('core.library_document_versions')->cascadeOnDelete();
            $table->ulid('document_id');
            $table->unsignedInteger('chunk_no');
            $table->unsignedInteger('page')->nullable();
            $table->text('body');
            $table->unique(['version_id', 'chunk_no']);
            $table->index('document_id');
        });
        // 'simple' configuration: no stemming, so Malay and English words are matched as written.
        DB::statement("ALTER TABLE core.library_chunks ADD COLUMN tsv tsvector GENERATED ALWAYS AS (to_tsvector('simple', body)) STORED");
        DB::statement('CREATE INDEX library_chunks_tsv_index ON core.library_chunks USING GIN (tsv)');
    }

    public function down(): void
    {
        Schema::dropIfExists('core.library_chunks');
    }
};
