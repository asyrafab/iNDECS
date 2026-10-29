<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The legacy site-plan scan field names the georeferenced image; plan_ often names an unreferenced copy.
        DB::statement('ALTER TABLE core.osc_legacy_papers ADD COLUMN IF NOT EXISTS pelan_tapak_scan text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE core.osc_legacy_papers DROP COLUMN IF EXISTS pelan_tapak_scan');
    }
};
