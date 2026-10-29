<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Same normalisation MeetingMapService applies to application lot numbers: "LOT 03899", "No. Lot 3899" and "3899" agree.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION core.gis_norm_lot(text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
            AS $$ SELECT regexp_replace(
                regexp_replace(upper(btrim(regexp_replace($1, '\s+', ' ', 'g'))), '^(NO\.? ?)?(LOT|LT)\.? ?', ''),
                '^0+(?=.)', '') $$
            SQL);
        DB::statement("CREATE INDEX gis_features_lot_norm_idx ON core.gis_features (snapshot_id, core.gis_norm_lot(attributes ->> 'lot'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS core.gis_features_lot_norm_idx');
        DB::statement('DROP FUNCTION IF EXISTS core.gis_norm_lot(text)');
    }
};
