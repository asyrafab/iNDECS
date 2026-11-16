<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Models\User;
use App\Services\Precheck\DraftChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    }
}
