<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dropped files are virus-scanned by the pre-check job, not in the upload request (ClamAV needs ~7 s per run;
 * an 80 MB package took ~55 s, past the 30 s request limit). Until scanned a file cannot be opened or placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.applicant_draft_documents', function (Blueprint $table): void {
            $table->boolean('scan_pending')->default(false);
            $table->string('rejected_reason', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('core.applicant_draft_documents', fn (Blueprint $table) => $table->dropColumn(['scan_pending', 'rejected_reason']));
    }
};
