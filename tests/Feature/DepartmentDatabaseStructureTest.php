<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApplicantDraft;
use App\Models\Application;
use App\Models\BangunanApplication;
use App\Models\CompletenessReview;
use App\Models\User;
use App\Support\DepartmentStructure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DepartmentDatabaseStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public static function types(): array
    {
        return array_map(fn ($type) => [$type], array_keys(DepartmentStructure::TYPES));
    }

    #[DataProvider('types')]
    public function test_all_department_extensions_use_the_same_key_and_restrictive_relationship(string $type): void
    {
        $table = DepartmentStructure::applicationTable($type);
        $key = DB::selectOne('SELECT a.attname FROM pg_index i JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) WHERE i.indrelid = ?::regclass AND i.indisprimary', [$table]);
        $this->assertSame('application_id', $key->attname);
        $fk = DB::selectOne("SELECT confdeltype FROM pg_constraint WHERE conrelid = ?::regclass AND confrelid = 'core.applications'::regclass AND contype = 'f'", [$table]);
        $this->assertSame('r', $fk->confdeltype);
        $this->assertTrue(Schema::hasColumns($table, ['application_id', 'created_at', 'updated_at']));

        $application = $this->application($type);
        if ($type === 'PKM') {
            $application->pkm()->create(['permission_category' => 'Baharu', 'permission_type' => 'Penuh', 'existing_land_use' => 'Tanah', 'proposed_land_use' => 'Kediaman']);
        } elseif (in_array($type, BangunanApplication::TYPES, true)) {
            $application->bangunan()->create(['building_category' => 'Kediaman', 'building_use' => 'Rumah']);
        }
        $this->assertDatabaseHas($table, ['application_id' => $application->id]);
        $this->assertSame($application->id, $application->fresh()->getKey());
    }

    public function test_shared_tables_are_in_core_and_department_fields_are_absent_from_shared_rows(): void
    {
        foreach (['applications', 'parties', 'sites', 'lots', 'application_documents', 'stage_histories', 'audit_logs', 'document_requirements', 'completeness_reviews', 'completeness_items'] as $name) {
            $this->assertTrue(Schema::hasTable('core.'.$name));
            $this->assertNull(DB::selectOne('SELECT to_regclass(?) AS name', ['public.'.$name])->name);
        }
        $this->assertFalse(Schema::hasColumn('core.applicant_drafts', 'building_category'));
        $this->assertFalse(Schema::hasColumn('core.applicant_drafts', 'lighting_types'));
        $this->assertFalse(Schema::hasColumn('core.paid_check_journeys', 'lighting_types'));
        $this->assertDatabaseHas('core.application_types', ['id' => 'PL', 'is_active' => true]);
        $this->assertDatabaseHas('core.application_types', ['id' => 'PKT', 'is_active' => false]);
    }

    public function test_database_rejects_a_cross_department_extension_even_for_raw_sql(): void
    {
        $application = $this->application('PL');
        $this->expectException(QueryException::class);
        DB::table('kejuruteraan.app_pkt_applications')->insert(['application_id' => $application->id]);
    }

    public function test_generic_completeness_cycles_can_attach_to_every_department(): void
    {
        foreach (['PKM', 'PB', 'LJUP', 'PL'] as $type) {
            $application = $this->application($type);
            $review = CompletenessReview::query()->create(['application_id' => $application->id, 'cycle_number' => 1, 'status' => 'in_progress', 'opened_at' => now()]);
            $this->assertSame($type, $review->application->application_type_id);
        }
    }

    #[DataProvider('types')]
    public function test_draft_model_round_trips_particulars_in_the_owning_extension(string $type): void
    {
        $draft = new ApplicantDraft(['application_type_id' => $type, 'title' => 'Draf', 'storeys' => 3, 'floor_area' => 180.5, 'site_conditions' => ['strata'],
            ...($type === 'LJUP' ? ['lighting_types' => ['BESI']] : [])]);
        $draft->user_id = User::factory()->create()->id;
        $draft->save();
        $this->assertSame(3, $draft->fresh()->storeys);
        $this->assertSame(180.5, $draft->fresh()->floor_area);
        $this->assertSame(['strata'], $draft->fresh()->site_conditions);
        $this->assertDatabaseHas(DepartmentStructure::draftTable($type), ['draft_id' => $draft->id, 'storeys' => 3]);
        $draft->storeys = 4;
        $draft->save();
        $draft->refresh();
        $this->assertSame(4, $draft->storeys);
        $this->assertSame(4, $draft->toArray()['storeys']);
    }

    public function test_switching_draft_type_keeps_previous_particulars_without_leaking_them_into_new_type(): void
    {
        $draft = new ApplicantDraft(['application_type_id' => 'LJUP', 'title' => 'Draf', 'lighting_types' => ['SOLAR']]);
        $draft->user_id = User::factory()->create()->id;
        $draft->save();
        $draft->lightingTypes(); // Warm the extension cache before switching.
        $draft->application_type_id = 'PB';
        $draft->building_category = 'teres';
        $draft->save();
        $this->assertNull($draft->fresh()->lighting_types);
        $this->assertDatabaseHas('kejuruteraan.app_ljup_draft_particulars', ['draft_id' => $draft->id]);
        $this->assertSame('teres', $draft->fresh()->building_category);
    }

    private function application(string $type): Application
    {
        return Application::query()->create(['application_type_id' => $type, 'application_reference' => $type.'/'.Str::ulid(), 'title' => 'Ujian',
            'submission_date' => '2026-11-17', 'submission_year' => 2026, 'responsible_department_id' => DepartmentStructure::TYPES[$type][2],
            'current_stage' => 'registration', 'current_status' => 'submitted']);
    }

    public function test_populated_upgrade_preserves_rows_ids_history_documents_snapshots_and_particulars(): void
    {
        $structure = require database_path('migrations/2026_11_17_000300_standardise_department_database_structure.php');
        $particulars = require database_path('migrations/2026_11_17_000400_move_department_particulars_to_extensions.php');
        $particulars->down();
        $structure->down();
        $actor = User::factory()->create();
        $now = '2026-11-01 10:00:00';
        $ids = [];
        foreach (['PKM', 'PB', 'LJUP', 'PL'] as $type) {
            $id = (string) Str::ulid();
            $ids[$type] = $id;
            DB::table('public.applications')->insert(['id' => $id, 'application_type_id' => $type, 'application_reference' => $type.'/OLD/1',
                'title' => 'Rekod asal', 'submission_date' => '2026-11-01', 'submission_year' => 2026,
                'responsible_department_id' => DepartmentStructure::TYPES[$type][2], 'current_stage' => 'registration', 'current_status' => 'submitted',
                'row_version' => 7, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('public.stage_histories')->insert(['application_id' => $id, 'new_stage' => 'registration', 'new_status' => 'submitted',
                'action' => 'original_registration', 'actor_id' => $actor->id, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('public.audit_logs')->insert(['auditable_type' => Application::class, 'auditable_id' => $id, 'event' => 'original', 'actor_id' => $actor->id,
                'after_values' => '{"original":true}', 'created_at' => $now]);
            DB::table('core.applicant_drafts')->insert(['id' => $id, 'user_id' => $actor->id, 'application_type_id' => $type, 'title' => 'Draf asal',
                'version' => 3, 'building_category' => 'teres', 'storeys' => 4, 'floor_area' => '123.45', 'site_conditions' => '["strata"]',
                'lighting_types' => $type === 'LJUP' ? '["BESI"]' : null, 'created_at' => $now, 'updated_at' => $now,
                'deleted_at' => $type === 'PB' ? $now : null]);
            DB::table('core.applicant_draft_revisions')->insert(['draft_id' => $id, 'actor_id' => $actor->id, 'version' => 3, 'snapshot' => '{"preserve":"exactly"}', 'created_at' => $now]);
        }
        DB::table('public.app_pkm_applications')->insert(['application_id' => $ids['PKM'], 'permission_category' => 'Baharu', 'permission_type' => 'Penuh',
            'existing_land_use' => 'Tanah', 'proposed_land_use' => 'Rumah', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('public.app_pkm_components')->insert(['id' => (string) Str::ulid(), 'application_id' => $ids['PKM'], 'name' => 'Komponen asal', 'created_at' => $now]);
        DB::table('bangunan.app_pb_applications')->insert(['application_id' => $ids['PB'], 'building_category' => 'Kediaman', 'building_use' => 'Rumah', 'created_at' => $now]);
        $reviewId = (string) Str::ulid();
        DB::table('public.app_pkm_completeness_reviews')->insert(['id' => $reviewId, 'application_id' => $ids['PKM'], 'cycle_number' => 2,
            'status' => 'returned', 'opened_at' => $now, 'opened_by' => $actor->id, 'decision_remarks' => 'Pindaan asal']);
        $documentType = DB::table('public.document_types')->value('id');
        DB::table('public.app_pkm_completeness_items')->insert(['review_id' => $reviewId, 'document_type_id' => $documentType, 'requirement_name' => 'Dokumen asal', 'status' => 'missing']);
        DB::table('public.application_documents')->insert(['id' => (string) Str::ulid(), 'application_id' => $ids['PKM'], 'document_type_id' => $documentType,
            'revision_number' => 1, 'uploaded_at' => $now, 'original_name' => 'asal.pdf', 'storage_path' => 'original/asal.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
            'checksum_sha256' => str_repeat('a', 64), 'uploaded_by' => $actor->id]);
        $paymentId = (string) Str::ulid();
        DB::table('core.check_payments')->insert(['id' => $paymentId, 'draft_id' => $ids['LJUP'], 'user_id' => $actor->id,
            'amount_sen' => 0, 'reference' => 'OLD/1', 'status' => 'paid', 'gateway' => 'fake', 'gateway_event_id' => 'old-event', 'paid_at' => $now]);
        $journeyId = (string) Str::ulid();
        DB::table('core.paid_check_journeys')->insert(['id' => $journeyId, 'draft_id' => $ids['LJUP'], 'application_id' => $ids['LJUP'], 'payment_id' => $paymentId,
            'state' => 'HANDED_OVER', 'lighting_types' => '["SOLAR"]', 'final_check_number' => 2, 'handed_over_at' => $now, 'created_at' => $now]);
        DB::table('core.accepted_submissions')->insert(['id' => (string) Str::ulid(), 'application_id' => $ids['LJUP'], 'document_manifest' => '[{"original":true}]',
            'document_set_hash' => str_repeat('b', 64), 'clearance_kind' => 'paid', 'payment_clearance_reference' => 'OLD/1', 'payment_cleared_at' => $now, 'accepted_at' => $now]);

        $tables = ['public.applications' => 'core.applications', 'public.stage_histories' => 'core.stage_histories', 'public.audit_logs' => 'core.audit_logs',
            'public.application_documents' => 'core.application_documents', 'public.app_pkm_applications' => 'perancang.app_pkm_applications',
            'public.app_pkm_components' => 'perancang.app_pkm_components', 'public.app_pkm_completeness_reviews' => 'core.completeness_reviews',
            'public.app_pkm_completeness_items' => 'core.completeness_items', 'bangunan.app_pb_applications' => 'bangunan.app_pb_applications',
            'core.applicant_draft_revisions' => 'core.applicant_draft_revisions', 'core.accepted_submissions' => 'core.accepted_submissions', 'core.check_payments' => 'core.check_payments'];
        $before = [];
        foreach ($tables as $old => $new) {
            $before[$new] = DB::table($old)->get()->map(fn ($row) => (array) $row)->all();
        }
        $structure->up();
        $particulars->up();
        foreach ($before as $table => $rows) {
            $this->assertEquals($rows, DB::table($table)->get()->map(fn ($row) => (array) $row)->all(), $table);
        }
        foreach ($ids as $type => $id) {
            $draft = ApplicantDraft::withTrashed()->findOrFail($id);
            $this->assertSame(4, $draft->storeys);
            $this->assertSame(123.45, $draft->floor_area);
            $this->assertSame(['strata'], $draft->site_conditions);
            $this->assertSame('teres', $draft->building_category);
            $this->assertSame(3, $draft->version);
        }
        $this->assertSame(['BESI'], ApplicantDraft::findOrFail($ids['LJUP'])->lightingTypes());
        $this->assertSame('["SOLAR"]', DB::table('kejuruteraan.app_ljup_check_particulars')->where('journey_id', $journeyId)->value('lighting_types'));
        $this->assertSame(['SOLAR'], Application::findOrFail($ids['LJUP'])->ljup->lighting_types);
        $this->assertDatabaseHas('landskap.app_pl_applications', ['application_id' => $ids['PL']]);
        $this->assertTrue(ApplicantDraft::withTrashed()->findOrFail($ids['PB'])->trashed());
        // Relocation retains the append-only trigger, rather than disabling/recreating history.
        $this->expectException(QueryException::class);
        DB::table('core.audit_logs')->where('auditable_id', $ids['PKM'])->update(['event' => 'rewritten']);
    }
}
