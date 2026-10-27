<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LegacyBangunan;
use App\Models\LegacyCcc;
use App\Models\LegacyPermohonan;
use App\Models\LegacyPerunding;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BangunanLegacyLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_loads_scrubbed_legacy_snapshot_and_is_idempotent(): void
    {
        $this->seed();

        $this->artisan('bangunan:sync-legacy')
            ->expectsOutputToContain('Penyegerakan baca-sahaja bangunan.legacy_* selesai.')
            ->assertSuccessful();

        $this->artisan('bangunan:sync-legacy')
            ->assertSuccessful();

        $this->assertSame(5, LegacyPermohonan::query()->count());
        $this->assertSame(1, LegacyPermohonan::query()->where('is_excluded_test_record', true)->count());
        $this->assertSame(3, LegacyPerunding::query()->count());
        $this->assertSame(5, LegacyBangunan::query()->count());
        $this->assertSame(4, LegacyCcc::query()->count());
    }

    public function test_jbang_officer_can_search_and_view_legacy_records_while_jpbd_officer_is_forbidden(): void
    {
        $this->seed();
        $this->artisan('bangunan:sync-legacy')->assertSuccessful();

        $jpbdOfficer = User::query()->where('email', 'pegawai@indecs.test')->firstOrFail();

        $jbangOfficer = User::factory()->create([
            'department_id' => 'JBANG',
            'role' => 'building_officer',
            'is_active' => true,
        ]);

        $this->assertFalse($jpbdOfficer->canAccessBangunanLegacy());
        $this->assertTrue($jbangOfficer->canAccessBangunanLegacy());

        $this->actingAs($jpbdOfficer)
            ->get(route('bangunan.legacy.index'))
            ->assertForbidden();

        $this->actingAs($jbangOfficer)
            ->get(route('bangunan.legacy.index'))
            ->assertOk()
            ->assertSee('Carian Rekod Warisan')
            ->assertSee('MBSP/40/20-50/13(PR1)')
            ->assertSee('MBSP/40/10-14/88')
            ->assertSee('MBSP/40/30-11/04')
            ->assertDontSee('MPSP/40/TEST/01');

        // Search by lot number
        $this->actingAs($jbangOfficer)
            ->get(route('bangunan.legacy.index', ['search' => '1269']))
            ->assertOk()
            ->assertSee('20253317')
            ->assertDontSee('20253402');

        // Detail page shows BANGUNAN1 blocks, PERUNDING, CCC, and shared file reference warning
        $record = LegacyPermohonan::query()->findOrFail(36298);
        $this->assertSame('LULUS', $record->effectiveStatusCode());
        $this->assertSame('Pelan Lulus', $record->effectiveStatusLabel());

        $this->actingAs($jbangOfficer)
            ->get(route('bangunan.legacy.show', $record))
            ->assertOk()
            ->assertSee('PERMIT PINDAAN KECIL KEPADA PELAN LULUS FASA 2')
            ->assertSee('Rumah Teres Kos Sederhana')
            ->assertSee('Firma Arkitek Sintetik Perai')
            ->assertSee('Perakuan Siap dan Pematuhan (CCC Penuh)')
            ->assertSee('Amaran rujukan fail dikongsi (needs_review)')
            ->assertSee('20221804');
    }

    public function test_district_scoped_jbang_officer_only_sees_legacy_records_in_assigned_district(): void
    {
        $this->seed();
        $this->artisan('bangunan:sync-legacy')->assertSuccessful();

        $spuBuildingOfficer = User::factory()->create([
            'department_id' => 'JBANG',
            'district' => 'Seberang Perai Utara',
            'role' => 'building_officer',
            'is_active' => true,
        ]);

        $this->actingAs($spuBuildingOfficer)
            ->get(route('bangunan.legacy.index'))
            ->assertOk()
            ->assertSee('MBSP/40/10-14/88')
            ->assertDontSee('MBSP/40/20-50/13(PR1)');

        $sptRecord = LegacyPermohonan::query()->findOrFail(36298);
        $spuRecord = LegacyPermohonan::query()->findOrFail(36312);

        $this->actingAs($spuBuildingOfficer)
            ->get(route('bangunan.legacy.show', $spuRecord))
            ->assertOk();

        $this->actingAs($spuBuildingOfficer)
            ->get(route('bangunan.legacy.show', $sptRecord))
            ->assertForbidden();

        // Excluded test record is never accessible via web lookup
        $excludedRecord = LegacyPermohonan::query()->findOrFail(9);
        $this->actingAs($spuBuildingOfficer)
            ->get(route('bangunan.legacy.show', $excludedRecord))
            ->assertForbidden();
    }

    public function test_legacy_models_refuse_eloquent_writes_and_deletes(): void
    {
        $this->seed();
        $this->artisan('bangunan:sync-legacy')->assertSuccessful();

        $record = LegacyPermohonan::query()->findOrFail(36298);

        $this->expectException(DomainException::class);
        $record->delete();
    }
}
