<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upload-first part 2: which dropped file each still-unconfirmed draft field was filled from.
 * Shape: {field: {document_id, name, preferred}}; cleared when the applicant saves the draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.applicant_drafts', fn (Blueprint $table) => $table->jsonb('prefill_sources')->nullable());
    }

    public function down(): void
    {
        Schema::table('core.applicant_drafts', fn (Blueprint $table) => $table->dropColumn('prefill_sources'));
    }
};
