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

    private const CORE_TABLES = [
        'departments', 'application_types', 'development_cases', 'applications',
        'parties', 'application_parties', 'sites', 'application_sites', 'lots',
        'application_lots', 'reference_sequences', 'stage_histories', 'audit_logs',
        'document_types', 'document_requirements', 'application_documents',
    ];

    public function up(): void
    {
        foreach (['core', 'perancang', 'bangunan', 'kejuruteraan', 'landskap'] as $schema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }
        foreach (self::CORE_TABLES as $table) {
            DB::statement("ALTER TABLE public.{$table} SET SCHEMA core");
        }
        DB::statement('ALTER TABLE public.app_pkm_applications SET SCHEMA perancang');
        DB::statement('ALTER TABLE public.app_pkm_components SET SCHEMA perancang');
        DB::statement('ALTER TABLE public.app_pkm_completeness_reviews SET SCHEMA core');
        DB::statement('ALTER TABLE core.app_pkm_completeness_reviews RENAME TO completeness_reviews');
        DB::statement('ALTER TABLE public.app_pkm_completeness_items SET SCHEMA core');
        DB::statement('ALTER TABLE core.app_pkm_completeness_items RENAME TO completeness_items');

        // Preserve the numeric compatibility identifier and its sequence, but align the primary key.
        DB::statement('ALTER TABLE bangunan.app_pb_applications ADD CONSTRAINT app_pb_legacy_id_unique UNIQUE (id)');
        DB::statement('ALTER TABLE bangunan.app_pb_applications DROP CONSTRAINT app_pb_applications_pkey');
        DB::statement('ALTER TABLE bangunan.app_pb_applications ADD PRIMARY KEY (application_id)');
        $this->restrictForeign('perancang.app_pkm_applications', 'app_pkm_applications_application_id_foreign', 'application_id', 'core.applications', 'id');
        $this->restrictForeign('perancang.app_pkm_components', 'app_pkm_components_application_id_foreign', 'application_id', 'perancang.app_pkm_applications', 'application_id');
        $this->restrictForeign('core.completeness_reviews', 'app_pkm_completeness_reviews_application_id_foreign', 'application_id', 'core.applications', 'id');
        $this->restrictForeign('core.completeness_items', 'app_pkm_completeness_items_review_id_foreign', 'review_id', 'core.completeness_reviews', 'id');

        foreach (['PKT' => 'Pelan Kerja Tanah', 'PKP' => 'Pelan Jalan dan Parit', 'PL' => 'Pelan Landskap'] as $type => $name) {
            $department = self::TYPES[$type][2];
            DB::table('core.departments')->insertOrIgnore(['id' => $department,
                'name' => $department === 'JLAND' ? 'Jabatan Landskap' : 'Jabatan Kejuruteraan',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('core.application_types')->insertOrIgnore(['id' => $type, 'name' => $name,
                'primary_department_id' => $department, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['PKT', 'PKP', 'LJUP', 'PL'] as $type) {
            $tableName = $this->applicationTable($type);
            Schema::create($tableName, function (Blueprint $table) use ($type): void {
                $table->ulid('application_id')->primary();
                $table->foreign('application_id')->references('id')->on('core.applications')->restrictOnDelete();
                match ($type) {
                    'PKT' => $this->earthworks($table),
                    'PKP' => $this->drainage($table),
                    'LJUP' => $table->jsonb('lighting_types')->nullable(),
                    'PL' => $this->landscape($table),
                };
                $table->timestamps();
            });
            DB::statement("INSERT INTO {$tableName} (application_id, created_at, updated_at) SELECT id, created_at, updated_at FROM core.applications WHERE application_type_id = ?", [$type]);
        }

        // A single guard enforces the same ownership invariant for every department extension.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION core.guard_department_extension() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE actual_type text;
            BEGIN
                SELECT application_type_id INTO actual_type FROM core.applications WHERE id = NEW.application_id;
                IF actual_type IS NULL OR NOT (actual_type = ANY(string_to_array(TG_ARGV[0], ','))) THEN
                    RAISE EXCEPTION 'Department extension does not match application type' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE OR REPLACE FUNCTION core.guard_application_department_type() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.application_type_id IS DISTINCT FROM OLD.application_type_id THEN
                    RAISE EXCEPTION 'An operational application type cannot be changed' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER application_department_type_guard BEFORE UPDATE OF application_type_id ON core.applications
                FOR EACH ROW EXECUTE FUNCTION core.guard_application_department_type();
            CREATE OR REPLACE FUNCTION core.initialise_department_extension() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                CASE NEW.application_type_id
                    WHEN 'PKT' THEN INSERT INTO kejuruteraan.app_pkt_applications (application_id, created_at, updated_at) VALUES (NEW.id, NEW.created_at, NEW.updated_at);
                    WHEN 'PKP' THEN INSERT INTO kejuruteraan.app_pkp_applications (application_id, created_at, updated_at) VALUES (NEW.id, NEW.created_at, NEW.updated_at);
                    WHEN 'LJUP' THEN INSERT INTO kejuruteraan.app_ljup_applications (application_id, created_at, updated_at) VALUES (NEW.id, NEW.created_at, NEW.updated_at);
                    WHEN 'PL' THEN INSERT INTO landskap.app_pl_applications (application_id, created_at, updated_at) VALUES (NEW.id, NEW.created_at, NEW.updated_at);
                    ELSE NULL;
                END CASE;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER application_department_extension_init AFTER INSERT ON core.applications
                FOR EACH ROW EXECUTE FUNCTION core.initialise_department_extension();
        SQL);
        $groups = [];
        foreach (self::TYPES as $type => $definition) {
            $groups[$this->applicationTable($type)][] = $type;
        }
        foreach ($groups as $table => $types) {
            $list = implode(',', $types);
            if (DB::selectOne("SELECT count(*) AS n FROM {$table} e JOIN core.applications a ON a.id = e.application_id WHERE a.application_type_id NOT IN (".implode(',', array_fill(0, count($types), '?')).')', $types)->n > 0) {
                throw new RuntimeException('Existing department extension has an unexpected application type: '.$table);
            }
            DB::unprepared("CREATE TRIGGER department_extension_type_guard BEFORE INSERT OR UPDATE OF application_id ON {$table} FOR EACH ROW EXECUTE FUNCTION core.guard_department_extension('{$list}')");
        }
    }

    public function down(): void
    {
        if (DB::table('core.applications')->exists() || DB::table('core.applicant_drafts')->exists()) {
            throw new RuntimeException('Operational data exists; use a reviewed data-preserving forward fix.');
        }
        DB::statement('DROP TRIGGER application_department_type_guard ON core.applications');
        DB::statement('DROP TRIGGER application_department_extension_init ON core.applications');
        DB::statement('DROP FUNCTION core.initialise_department_extension()');
        DB::statement('DROP FUNCTION core.guard_application_department_type()');
        foreach (array_unique(array_map(fn ($type) => $this->applicationTable($type), array_keys(self::TYPES))) as $table) {
            DB::statement("DROP TRIGGER department_extension_type_guard ON {$table}");
        }
        DB::statement('DROP FUNCTION core.guard_department_extension()');
        foreach (['PKT', 'PKP', 'LJUP', 'PL'] as $type) {
            Schema::drop($this->applicationTable($type));
        }
        DB::statement('ALTER TABLE bangunan.app_pb_applications DROP CONSTRAINT app_pb_applications_pkey');
        DB::statement('ALTER TABLE bangunan.app_pb_applications ADD PRIMARY KEY (id)');
        DB::statement('ALTER TABLE bangunan.app_pb_applications DROP CONSTRAINT app_pb_legacy_id_unique');
        $this->restrictForeign('perancang.app_pkm_applications', 'app_pkm_applications_application_id_foreign', 'application_id', 'core.applications', 'id', 'CASCADE');
        $this->restrictForeign('perancang.app_pkm_components', 'app_pkm_components_application_id_foreign', 'application_id', 'perancang.app_pkm_applications', 'application_id', 'CASCADE');
        $this->restrictForeign('core.completeness_reviews', 'app_pkm_completeness_reviews_application_id_foreign', 'application_id', 'core.applications', 'id', 'CASCADE');
        $this->restrictForeign('core.completeness_items', 'app_pkm_completeness_items_review_id_foreign', 'review_id', 'core.completeness_reviews', 'id', 'CASCADE');
        DB::statement('ALTER TABLE core.completeness_items RENAME TO app_pkm_completeness_items');
        DB::statement('ALTER TABLE core.app_pkm_completeness_items SET SCHEMA public');
        DB::statement('ALTER TABLE core.completeness_reviews RENAME TO app_pkm_completeness_reviews');
        DB::statement('ALTER TABLE core.app_pkm_completeness_reviews SET SCHEMA public');
        DB::statement('ALTER TABLE perancang.app_pkm_components SET SCHEMA public');
        DB::statement('ALTER TABLE perancang.app_pkm_applications SET SCHEMA public');
        foreach (array_reverse(self::CORE_TABLES) as $table) {
            DB::statement("ALTER TABLE core.{$table} SET SCHEMA public");
        }
        // Keep schemas and catalogue rows: schema rollback must not retire owner-maintained configuration.
    }

    private function restrictForeign(string $table, string $constraint, string $column, string $target, string $key, string $delete = 'RESTRICT'): void
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES {$target} ({$key}) ON DELETE {$delete}");
    }

    private function earthworks(Blueprint $table): void
    {
        foreach (['existing_level', 'platform_level', 'cut_volume', 'fill_volume', 'slope_gradient'] as $field) {
            $table->decimal($field, 14, 4)->nullable();
        }
        $table->text('retaining_structures')->nullable();
        $table->text('erosion_sediment_control')->nullable();
        $table->text('technical_notes')->nullable();
    }

    private function drainage(Blueprint $table): void
    {
        foreach (['catchment_area', 'design_flow_rate', 'drain_width', 'drain_depth', 'drain_reserve', 'detention_volume'] as $field) {
            $table->decimal($field, 14, 4)->nullable();
        }
        $table->text('outfall_location')->nullable();
        $table->text('technical_notes')->nullable();
    }

    private function landscape(Blueprint $table): void
    {
        $table->string('landscape_category', 100)->nullable();
        foreach (['open_space_area', 'softscape_area', 'hardscape_area'] as $field) {
            $table->decimal($field, 14, 4)->nullable();
        }
        foreach (['existing_trees', 'retained_trees', 'proposed_trees'] as $field) {
            $table->unsignedInteger($field)->nullable();
        }
        foreach (['proposed_species', 'buffer_planting', 'recreation_facilities', 'replacement_conditions'] as $field) {
            $table->text($field)->nullable();
        }
    }

    private function applicationTable(string $type): string
    {
        [$schema, $prefix] = self::TYPES[$type];

        return $schema.'.'.$prefix.'_applications';
    }
};
