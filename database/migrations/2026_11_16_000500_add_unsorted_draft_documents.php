<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Upload-first drafts: a dropped file waits unsorted (no item, no document type) until AIRS or the applicant places it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.applicant_draft_documents', function (Blueprint $table): void {
            $table->string('item_code', 40)->nullable()->change();
            $table->unsignedBigInteger('document_type_id')->nullable()->change();
            $table->string('sort_source', 8)->nullable();
            $table->integer('sort_score')->nullable();
        });
        Schema::table('core.applicant_drafts', function (Blueprint $table): void {
            $table->boolean('title_provisional')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('core.applicant_drafts', fn (Blueprint $table) => $table->dropColumn('title_provisional'));
        Schema::table('core.applicant_draft_documents', function (Blueprint $table): void {
            $table->dropColumn(['sort_source', 'sort_score']);
        });
    }
};
