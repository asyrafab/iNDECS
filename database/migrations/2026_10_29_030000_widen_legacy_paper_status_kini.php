<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // In the MJKP layer status_kini holds the current owner or applicant name, not a short code.
        DB::statement('ALTER TABLE core.osc_legacy_papers ALTER COLUMN status_kini TYPE text');
    }

    public function down(): void
    {
        // Widening is not safely reversible once real values exist; keep the wider type.
    }
};
