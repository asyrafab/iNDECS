<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Models\User;
use App\Services\DocumentCatalogueService;
use App\Services\Precheck\DraftChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The committed LJUP checklist (database/catalogues/ljup.v1.json) stays valid and publishable. */
final class LjupCatalogueFileTest extends TestCase
{
    use BuildsLjupJourney, RefreshDatabase;

    public function test_committed_ljup_catalogue_publishes_and_drives_the_draft_checklist(): void
    {
        $this->seed();
        $senior = User::factory()->create(['email' => 'kanan@indecs.test', 'department_id' => 'JKEJ', 'role' => 'senior_engineering_officer']);

        $this->artisan('catalogue:publish', ['type' => 'LJUP', 'file' => base_path('database/catalogues/ljup.v1.json'), '--actor' => $senior->email])
            ->assertSuccessful();

        $this->assertSame(1, (int) DB::table('core.document_catalogues')->where('application_type_id', 'LJUP')->value('version'));
        $checklist = app(DraftChecklist::class)->for($this->ljupDraft($this->applicant()));
        $this->assertTrue($checklist['available']);
        $this->assertCount(9, $checklist['items']);
        $this->assertSame(5, $checklist['required_total']);
        $this->assertSame(['pelan_lampu_jalan', 'pelan_kebenaran_merancang', 'pelan_jalan_parit', 'gambar_tapak', 'borang_pengemukaan'],
            array_values(array_map(fn ($item) => $item['code'], array_filter($checklist['items'], fn ($item) => $item['is_required']))));
        // Approved planning/building plans in the pilot package were 25–30 MB: plan items allow 50 MB.
        $this->assertSame(51200, (int) DB::table('document_types')->where('code', 'kej_ljup_pelan_kebenaran_merancang')->value('max_file_size_kb'));
        $this->assertSame(51200, (int) DB::table('document_types')->where('code', 'kej_ljup_pelan_lampu_jalan')->value('max_file_size_kb'));
    }

    public function test_item_size_limit_is_bounded(): void
    {
        $this->seed();
        $senior = User::factory()->create(['department_id' => 'JKEJ', 'role' => 'senior_engineering_officer']);
        $this->expectException(ValidationException::class);
        app(DocumentCatalogueService::class)->publish('LJUP', ['version' => 0, 'source_reference' => 'Ujian', 'items' => [
            ['code' => 'pelan', 'name' => 'Pelan', 'is_required' => true, 'max_file_size_kb' => 204800],
        ]], $senior);
    }

    public function test_every_item_has_sorting_keywords(): void
    {
        $catalogue = json_decode((string) file_get_contents(base_path('database/catalogues/ljup.v1.json')), true);
        foreach ($catalogue['items'] as $item) {
            $this->assertNotEmpty($item['precheck_keywords'] ?? [], $item['code'].' has no sorting keywords.');
        }
        $this->assertContains('LUX', collect($catalogue['items'])->firstWhere('code', 'laporan_fotometrik')['precheck_keywords']);
    }
}
