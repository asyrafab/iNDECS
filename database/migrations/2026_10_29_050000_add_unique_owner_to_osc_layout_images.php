<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** One positioned layout plan per owner: replacing a plan updates its row, it never adds a second one. */
    public function up(): void
    {
        $duplicate = DB::selectOne(
            'SELECT owner_type, owner_id FROM core.osc_layout_images GROUP BY owner_type, owner_id HAVING count(*) > 1 LIMIT 1',
        );
        if ($duplicate !== null) {
            throw new RuntimeException("Duplicate layout images for {$duplicate->owner_type} {$duplicate->owner_id}; resolve them before migrating.");
        }

        DB::statement('CREATE UNIQUE INDEX osc_layout_images_owner_unique ON core.osc_layout_images (owner_type, owner_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS core.osc_layout_images_owner_unique');
    }
};
