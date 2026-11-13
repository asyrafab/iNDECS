<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Required checklist documents that were absent at handover ("Dokumen wajib tidak dilampirkan").
// The application is still accepted; the officer decides exemption or rejection.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core.accepted_submissions', function (Blueprint $table): void {
            $table->jsonb('missing_required_documents')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('core.accepted_submissions', fn (Blueprint $table) => $table->dropColumn('missing_required_documents'));
    }
};
