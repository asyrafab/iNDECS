<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Freeze the historical storage map: future runtime registry additions must not alter this migration.
    private const TYPES = [
        'PKM' => ['perancang', 'app_pkm', 'JPBD'],
        'PB' => ['bangunan', 'app_pb', 'JBANG'],
        'PBK' => ['bangunan', 'app_pb', 'JBANG'],
        'PBS' => ['bangunan', 'app_pb', 'JBANG'],
        'PMT' => ['bangunan', 'app_pb', 'JBANG'],
        'TEL' => ['bangunan', 'app_pb', 'JBANG'],
        'PKT' => ['kejuruteraan', 'app_pkt', 'JKEJ'],
        'PKP' => ['kejuruteraan', 'app_pkp', 'JKEJ'],
        'LJUP' => ['kejuruteraan', 'app_ljup', 'JKEJ'],
        'PL' => ['landskap', 'app_pl', 'JLAND'],
    ];

    private const FIELDS = ['work_type', 'building_category', 'storeys', 'floor_area', 'site_conditions'];

    public function up(): void
    {
        DB::statement('LOCK TABLE core.applicant_drafts, core.paid_check_journeys IN ACCESS EXCLUSIVE MODE');
        if (DB::table('core.applicant_drafts')->whereNotIn('application_type_id', array_keys(self::TYPES))->exists()
            || DB::table('core.applicant_drafts')->where('application_type_id', '!=', 'LJUP')->whereNotNull('lighting_types')->exists()
            || DB::table('core.paid_check_journeys as j')->join('core.applications as a', 'a.id', '=', 'j.application_id')->where('a.application_type_id', '!=', 'LJUP')->exists()) {
            throw new RuntimeException('Unexpected draft/journey type or lighting data; review before migrating. No data was discarded.');
        }
        $groups = [];
        foreach (self::TYPES as $type => $definition) {
            $groups[$this->draftTable($type)][] = $type;
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core.guard_draft_extension() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE actual_type text;
            BEGIN
                SELECT application_type_id INTO actual_type FROM core.applicant_drafts WHERE id = NEW.draft_id;
                IF actual_type IS NULL OR NOT (actual_type = ANY(string_to_array(TG_ARGV[0], ','))) THEN
                    RAISE EXCEPTION 'Draft extension does not match application type' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE OR REPLACE FUNCTION core.guard_ljup_check_extension() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM core.paid_check_journeys j JOIN core.applications a ON a.id = j.application_id
                    WHERE j.id = NEW.journey_id AND a.application_type_id = 'LJUP') THEN
                    RAISE EXCEPTION 'Lighting check particulars require an LJUP journey' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
        SQL);
        foreach ($groups as $tableName => $types) {
            $lighting = in_array('LJUP', $types, true);
            Schema::create($tableName, function (Blueprint $table) use ($lighting): void {
                $table->ulid('draft_id')->primary();
                $table->foreign('draft_id')->references('id')->on('core.applicant_drafts')->restrictOnDelete();
                $table->string('work_type', 30)->nullable();
                $table->string('building_category', 30)->nullable();
                $table->unsignedSmallInteger('storeys')->nullable();
                $table->decimal('floor_area', 12, 2)->nullable();
                $table->jsonb('site_conditions')->nullable();
                if ($lighting) {
                    $table->jsonb('lighting_types')->nullable();
                }
                $table->timestamps();
            });
            $columns = implode(', ', self::FIELDS).($lighting ? ', lighting_types' : '');
            DB::statement("INSERT INTO {$tableName} (draft_id, {$columns}, created_at, updated_at) SELECT id, {$columns}, created_at, updated_at FROM core.applicant_drafts WHERE application_type_id IN (".implode(',', array_fill(0, count($types), '?')).')', $types);
            $list = implode(',', $types);
            DB::unprepared("CREATE TRIGGER draft_extension_type_guard BEFORE INSERT OR UPDATE OF draft_id ON {$tableName} FOR EACH ROW EXECUTE FUNCTION core.guard_draft_extension('{$list}')");
        }
        Schema::create('kejuruteraan.app_ljup_check_particulars', function (Blueprint $table): void {
            $table->ulid('journey_id')->primary();
            $table->foreign('journey_id')->references('id')->on('core.paid_check_journeys')->restrictOnDelete();
            $table->jsonb('lighting_types');
            $table->timestamps();
        });
        DB::statement('INSERT INTO kejuruteraan.app_ljup_check_particulars (journey_id, lighting_types, created_at, updated_at) SELECT id, lighting_types, created_at, updated_at FROM core.paid_check_journeys');
        DB::unprepared('CREATE TRIGGER ljup_check_extension_type_guard BEFORE INSERT OR UPDATE OF journey_id ON kejuruteraan.app_ljup_check_particulars FOR EACH ROW EXECUTE FUNCTION core.guard_ljup_check_extension()');
        DB::statement('UPDATE kejuruteraan.app_ljup_applications e SET lighting_types = p.lighting_types FROM core.paid_check_journeys j JOIN kejuruteraan.app_ljup_check_particulars p ON p.journey_id = j.id WHERE e.application_id = j.application_id');
        Schema::table('core.applicant_drafts', fn (Blueprint $table) => $table->dropColumn([...self::FIELDS, 'lighting_types']));
        Schema::table('core.paid_check_journeys', fn (Blueprint $table) => $table->dropColumn('lighting_types'));
    }

    public function down(): void
    {
        if (DB::table('core.applicant_drafts')->exists() || DB::table('core.applications')->exists()) {
            throw new RuntimeException('Operational data exists; use a reviewed data-preserving forward fix.');
        }
        Schema::table('core.applicant_drafts', function (Blueprint $table): void {
            $table->string('work_type', 30)->nullable();
            $table->string('building_category', 30)->nullable();
            $table->unsignedSmallInteger('storeys')->nullable();
            $table->decimal('floor_area', 12, 2)->nullable();
            $table->jsonb('site_conditions')->nullable();
            $table->jsonb('lighting_types')->nullable();
        });
        Schema::table('core.paid_check_journeys', fn (Blueprint $table) => $table->jsonb('lighting_types'));
        Schema::drop('kejuruteraan.app_ljup_check_particulars');
        foreach (array_unique(array_map(fn ($type) => $this->draftTable($type), array_keys(self::TYPES))) as $table) {
            Schema::drop($table);
        }
        DB::statement('DROP FUNCTION core.guard_draft_extension()');
        DB::statement('DROP FUNCTION core.guard_ljup_check_extension()');
    }

    private function draftTable(string $type): string
    {
        [$schema, $prefix] = self::TYPES[$type];

        return $schema.'.'.$prefix.'_draft_particulars';
    }
};
