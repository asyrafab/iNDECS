<?php

namespace Tests\Feature\Checklist;

use App\Models\User;
use App\Services\BangunanDocumentCatalogueService;
use App\Services\Checklist\ChecklistRuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChecklistRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
    }

    public function test_unconditional_items_always_apply(): void
    {
        $this->publish([$this->item('pelan_lantai', 'Pelan lantai')]);
        $docs = app(ChecklistRuleEvaluator::class)->documentsFor('TEL', $this->particulars());
        $this->assertSame(['pelan_lantai'], $docs->pluck('code')->all());
        $this->assertFalse($docs->first()['conditional']);
    }

    public function test_lists_and_site_condition_overlap_match(): void
    {
        $this->publish([
            $this->item('pelan_lantai', 'Pelan lantai'),
            $this->item('kebenaran_jiran', 'Surat jiran', ['work_types' => ['tambahan'], 'building_categories' => ['teres', 'berkembar']]),
            $this->item('laporan_geoteknik', 'Laporan geoteknik', ['site_conditions' => ['lereng_bukit', 'rizab_sungai']]),
        ]);
        $evaluator = app(ChecklistRuleEvaluator::class);
        $this->assertSame(['pelan_lantai', 'kebenaran_jiran'], $evaluator->documentsFor('TEL', $this->particulars(['work_type' => 'tambahan', 'building_category' => 'teres']))->pluck('code')->all());
        $this->assertSame(['pelan_lantai'], $evaluator->documentsFor('TEL', $this->particulars(['work_type' => 'tambahan', 'building_category' => 'komersial']))->pluck('code')->all());
        $this->assertSame(['pelan_lantai', 'laporan_geoteknik'], $evaluator->documentsFor('TEL', $this->particulars(['site_conditions' => ['strata', 'rizab_sungai']]))->pluck('code')->all());
    }

    public function test_threshold_is_inclusive_and_missing_particular_never_matches(): void
    {
        $this->publish([
            $this->item('pelan_lantai', 'Pelan lantai'),
            $this->item('pelan_struktur', 'Pelan struktur', ['storeys_min' => 3]),
            $this->item('laporan_bomba', 'Laporan bomba', ['floor_area_min' => 500]),
        ]);
        $evaluator = app(ChecklistRuleEvaluator::class);
        $this->assertSame(['pelan_lantai', 'pelan_struktur'], $evaluator->documentsFor('TEL', $this->particulars(['storeys' => 3]))->pluck('code')->all());
        $this->assertSame(['pelan_lantai'], $evaluator->documentsFor('TEL', $this->particulars(['storeys' => 2]))->pluck('code')->all());
        $this->assertSame(['pelan_lantai'], $evaluator->documentsFor('TEL', $this->particulars())->pluck('code')->all());
        $this->assertSame(['pelan_lantai', 'laporan_bomba'], $evaluator->documentsFor('TEL', $this->particulars(['floor_area' => 500.0]))->pluck('code')->all());
        $this->assertTrue($evaluator->documentsFor('TEL', $this->particulars(['storeys' => 4]))->firstWhere('code', 'pelan_struktur')['conditional']);
    }

    public function test_publish_rejects_unknown_values_and_bad_thresholds_and_keeps_version(): void
    {
        $this->publish([$this->item('pelan_lantai', 'Pelan lantai')]);
        $cases = [
            [['work_types' => ['kilang_roket']], 'items.0.conditions.work_types.0'],
            [['storeys_min' => -1], 'items.0.conditions.storeys_min'],
            [['floor_area_min' => 0], 'items.0.conditions.floor_area_min'],
            [['site_conditions' => ['bulan']], 'items.0.conditions.site_conditions.0'],
            [['warna' => ['merah']], 'items.0.conditions'],
        ];
        foreach ($cases as [$conditions, $key]) {
            try {
                $this->publish([$this->item('pelan_lantai', 'Pelan lantai', $conditions)], 1);
                $this->fail("Conditions must be rejected: {$key}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($key, $e->errors());
            }
        }
        $this->assertSame(1, (int) DB::table('core.document_catalogues')->where('application_type_id', 'TEL')->value('version'));
    }

    public function test_publish_stores_guidance_and_conditions_and_audits_them(): void
    {
        $this->publish([
            $this->item('pelan_lantai', 'Pelan lantai'),
            $this->item('pelan_struktur', 'Pelan struktur', ['storeys_min' => 3], 'Dimeterai oleh jurutera profesional.'),
        ]);
        $doc = app(ChecklistRuleEvaluator::class)->documentsFor('TEL', $this->particulars(['storeys' => 5]))->firstWhere('code', 'pelan_struktur');
        $this->assertSame('Dimeterai oleh jurutera profesional.', $doc['guidance']);
        $audit = DB::table('audit_logs')->where('event', 'document_catalogue_published')->latest('id')->value('after_values');
        $this->assertSame(['storeys_min' => 3], json_decode($audit, true)['items'][1]['conditions']);
    }

    private function publish(array $items, int $version = 0): void
    {
        app(BangunanDocumentCatalogueService::class)->publish('TEL', ['version' => $version, 'source_reference' => 'Rujukan ujian', 'items' => $items], $this->admin);
    }

    private function item(string $code, string $name, ?array $conditions = null, ?string $guidance = null): array
    {
        return ['code' => $code, 'name' => $name, 'is_required' => true, 'guidance' => $guidance, 'conditions' => $conditions];
    }

    private function particulars(array $overrides = []): array
    {
        return array_replace(['work_type' => null, 'building_category' => null, 'storeys' => null, 'floor_area' => null, 'site_conditions' => []], $overrides);
    }
}
