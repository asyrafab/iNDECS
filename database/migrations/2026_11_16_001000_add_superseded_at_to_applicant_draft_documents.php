<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A replaced upload is marked, not inferred from "newest N": raising an item's file limit must never bring
 * back a file the applicant replaced, and lowering it must never hide a current file.
 * Until now every item held one file, so all but the newest row of each item are replaced history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.applicant_draft_documents', fn (Blueprint $table) => $table->timestamp('superseded_at')->nullable());
        DB::statement(<<<'SQL'
            UPDATE core.applicant_draft_documents d SET superseded_at = now()
            FROM (SELECT id, row_number() OVER (PARTITION BY draft_id, item_code ORDER BY sequence DESC) AS rank
                  FROM core.applicant_draft_documents WHERE item_code IS NOT NULL) ranked
            WHERE d.id = ranked.id AND ranked.rank > 1
        SQL);
    }

    public function down(): void
    {
        Schema::table('core.applicant_draft_documents', fn (Blueprint $table) => $table->dropColumn('superseded_at'));
    }
};
