<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SRIDS = [
        990001 => [
            'srtext' => 'PROJCS["My_G_Cassini_Pulau_Pinang_&_Seberang_Perai",GEOGCS["GCS_GDM_2000",DATUM["D_GDM_2000",SPHEROID["GRS_1980",6378137.0,298.257222101]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Cassini"],PARAMETER["False_Easting",0.0],PARAMETER["False_Northing",0.0],PARAMETER["Central_Meridian",100.3446556],PARAMETER["Scale_Factor",1.0],PARAMETER["Latitude_Of_Origin",5.420890092],UNIT["Meter",1.0]]',
            'proj4text' => '+proj=cass +lat_0=5.420890092 +lon_0=100.3446556 +x_0=0 +y_0=0 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
        ],
        990002 => [
            'srtext' => 'PROJCS["Malaysia_Cassini_Penang",GEOGCS["GCS_Kertau",DATUM["D_Kertau",SPHEROID["Everest_1830_Modified",6377304.063,300.8017]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Cassini"],PARAMETER["False_Easting",0.0],PARAMETER["False_Northing",0.0],PARAMETER["Central_Meridian",100.3458694],PARAMETER["Scale_Factor",1.0],PARAMETER["Latitude_Of_Origin",5.421325],UNIT["Meter",1.0]]',
            'proj4text' => '+proj=cass +lat_0=5.421325 +lon_0=100.3458694 +x_0=0 +y_0=0 +a=6377304.063 +rf=300.8017 +towgs84=-11,851,5,0,0,0,0 +units=m +no_defs',
        ],
    ];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        foreach (self::SRIDS as $srid => $def) {
            DB::delete('DELETE FROM spatial_ref_sys WHERE srid = ?', [$srid]);
            DB::insert(
                'INSERT INTO spatial_ref_sys (srid, auth_name, auth_srid, srtext, proj4text) VALUES (?, ?, ?, ?, ?)',
                [$srid, 'MBSP', $srid, $def['srtext'], $def['proj4text']],
            );
        }

        Schema::create('core.gis_layers', function (Blueprint $table): void {
            $table->string('id', 50)->primary();
            $table->string('label');
            $table->string('kind', 20);
            $table->text('source_url');
            $table->text('where_clause')->default('1=1');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->jsonb('match_attributes')->nullable();
            $table->unsignedBigInteger('active_snapshot_id')->nullable();
            $table->timestamps();
        });

        Schema::create('core.gis_layer_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('layer_id', 50);
            $table->string('status', 20);
            $table->integer('feature_count')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreign('layer_id')->references('id')->on('core.gis_layers')->cascadeOnDelete();
            $table->index(['layer_id', 'status']);
        });
        Schema::table('core.gis_layers', function (Blueprint $table): void {
            $table->foreign('active_snapshot_id')->references('id')->on('core.gis_layer_snapshots')->nullOnDelete();
        });

        Schema::create('core.gis_features', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('snapshot_id');
            $table->jsonb('attributes')->default('{}');
            $table->foreign('snapshot_id')->references('id')->on('core.gis_layer_snapshots')->cascadeOnDelete();
            $table->index('snapshot_id');
        });
        DB::statement('ALTER TABLE core.gis_features ADD COLUMN geom geometry(Geometry, 4326) NOT NULL');
        DB::statement('ALTER TABLE core.gis_features ADD COLUMN geom_web geometry(Geometry, 3857) NOT NULL');
        DB::statement('CREATE INDEX gis_features_geom_gist ON core.gis_features USING GIST (geom)');
        DB::statement('CREATE INDEX gis_features_geom_web_gist ON core.gis_features USING GIST (geom_web)');

        DB::statement('ALTER TABLE sites ADD COLUMN boundary geometry(MultiPolygon, 4326)');
        DB::statement('CREATE INDEX sites_boundary_gist ON sites USING GIST (boundary)');
        DB::statement('ALTER TABLE core.osc_agenda_items ADD COLUMN boundary geometry(MultiPolygon, 4326)');
        DB::statement('CREATE INDEX osc_agenda_items_boundary_gist ON core.osc_agenda_items USING GIST (boundary)');

        Schema::create('core.osc_legacy_papers', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->text('title');
            $table->string('status_code', 5)->nullable();
            $table->string('status_kini', 5)->nullable();
            $table->string('year', 10)->nullable();
            $table->string('month', 20)->nullable();
            $table->string('mukim')->nullable();
            $table->string('seksyen')->nullable();
            $table->string('kategori')->nullable();
            $table->string('rujukan')->nullable();
            $table->string('no_fail')->nullable();
            $table->string('plan_file')->nullable();
            $table->timestampTz('imported_at');
        });
        DB::statement('ALTER TABLE core.osc_legacy_papers ADD COLUMN geom geometry(Point, 4326)');
        DB::statement('CREATE INDEX osc_legacy_papers_geom_gist ON core.osc_legacy_papers USING GIST (geom)');

        Schema::create('core.osc_layout_images', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('owner_type', 20);
            $table->string('owner_id', 26);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->integer('source_srid')->nullable();
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });
        // Ring order: top-left, top-right, bottom-right, bottom-left, top-left.
        DB::statement('ALTER TABLE core.osc_layout_images ADD COLUMN corners geometry(Polygon, 4326) NOT NULL');
        DB::statement("ALTER TABLE core.osc_layout_images ADD CONSTRAINT osc_layout_images_owner_type_check CHECK (owner_type IN ('legacy', 'application', 'agenda_item'))");
    }

    public function down(): void
    {
        if (DB::table('core.gis_features')->exists()
            || DB::table('core.osc_legacy_papers')->exists()
            || DB::table('core.osc_layout_images')->exists()
            || DB::table('sites')->whereNotNull('boundary')->exists()
            || DB::table('core.osc_agenda_items')->whereNotNull('boundary')->exists()) {
            throw new RuntimeException('GIS data exists; use a data-preserving forward fix.');
        }

        Schema::dropIfExists('core.osc_layout_images');
        Schema::dropIfExists('core.osc_legacy_papers');
        DB::statement('DROP INDEX IF EXISTS core.osc_agenda_items_boundary_gist');
        DB::statement('ALTER TABLE core.osc_agenda_items DROP COLUMN IF EXISTS boundary');
        DB::statement('DROP INDEX IF EXISTS sites_boundary_gist');
        DB::statement('ALTER TABLE sites DROP COLUMN IF EXISTS boundary');
        Schema::dropIfExists('core.gis_features');
        Schema::table('core.gis_layers', function (Blueprint $table): void {
            $table->dropForeign(['active_snapshot_id']);
        });
        Schema::dropIfExists('core.gis_layer_snapshots');
        Schema::dropIfExists('core.gis_layers');
        DB::delete('DELETE FROM spatial_ref_sys WHERE srid IN (990001, 990002)');
    }
};
