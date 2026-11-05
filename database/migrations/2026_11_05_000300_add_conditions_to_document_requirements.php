<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ADR-362: conditional checklist rules on the versioned Bangunan document catalogue. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->jsonb('conditions')->nullable();
            $table->text('guidance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->dropColumn(['conditions', 'guidance']);
        });
    }
};
