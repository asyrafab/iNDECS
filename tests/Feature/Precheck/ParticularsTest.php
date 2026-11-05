<?php

namespace Tests\Feature\Precheck;

use App\Models\ApplicantDraft;
use App\Models\User;
use App\Services\BangunanDocumentCatalogueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Portal\BuildsApplicants;
use Tests\TestCase;

class ParticularsTest extends TestCase
{
    use BuildsApplicants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_draft_saves_particulars_and_snapshots_them(): void
    {
        $this->actingAs($this->applicant())->post(route('portal.drafts.store'), $this->tel([
            'work_type' => 'tambahan', 'building_category' => 'teres', 'storeys' => '3', 'floor_area' => '180.5', 'site_conditions' => ['strata'],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $draft = ApplicantDraft::query()->firstOrFail();
        $this->assertSame(['work_type' => 'tambahan', 'building_category' => 'teres', 'storeys' => 3, 'floor_area' => 180.5, 'site_conditions' => ['strata']], $draft->particulars());
        $snapshot = json_decode(DB::table('core.applicant_draft_revisions')->value('snapshot'), true);
        $this->assertSame('tambahan', $snapshot['work_type']);
        $this->assertSame(['strata'], $snapshot['site_conditions']);

        $this->put(route('portal.drafts.update', $draft), $this->tel(['storeys' => '2', 'version' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(['work_type' => null, 'building_category' => null, 'storeys' => 2, 'floor_area' => null, 'site_conditions' => []], $draft->fresh()->particulars());
    }

    public function test_particulars_reject_unknown_values(): void
    {
        $this->actingAs($this->applicant())->post(route('portal.drafts.store'), $this->tel([
            'work_type' => 'kapal', 'building_category' => 'istana', 'storeys' => '0', 'floor_area' => '-5', 'site_conditions' => ['bulan'],
        ]))->assertSessionHasErrors(['work_type', 'building_category', 'storeys', 'floor_area', 'site_conditions.0']);
        $this->assertDatabaseCount('core.applicant_drafts', 0);
    }

    public function test_catalogue_keywords_are_trimmed_uppercased_and_bounded(): void
    {
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
        $service = app(BangunanDocumentCatalogueService::class);
        $service->publish('TEL', ['version' => 0, 'source_reference' => 'Ujian', 'items' => [
            ['code' => 'geran', 'name' => 'Geran tanah', 'is_required' => true, 'precheck_keywords' => [' hakmilik ', 'No. Lot', '']],
        ]], $admin);
        $this->assertSame(['HAKMILIK', 'NO. LOT'], DB::table('document_requirements')->where('is_active', true)->get()
            ->map(fn ($row) => json_decode((string) $row->precheck_keywords, true))->filter()->first());

        $this->expectException(ValidationException::class);
        $service->publish('TEL', ['version' => 1, 'source_reference' => 'Ujian', 'items' => [
            ['code' => 'geran', 'name' => 'Geran tanah', 'is_required' => true, 'precheck_keywords' => array_map(fn ($i) => "KATA{$i}", range(1, 11))],
        ]], $admin);
    }

    private function tel(array $overrides = []): array
    {
        return array_replace(['application_type_id' => 'TEL', 'title' => 'Menara telekomunikasi', 'site_address' => 'Lot 1', 'notes' => null], $overrides);
    }
}
