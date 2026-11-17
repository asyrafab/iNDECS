<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Models\ApplicantDraft;
use App\Models\ApplicationType;
use App\Models\User;
use App\Services\DocumentCatalogueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Portal\BuildsApplicants;
use Tests\TestCase;

final class LjupSetupTest extends TestCase
{
    use BuildsApplicants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function catalogue(): array
    {
        return ['version' => 0, 'source_reference' => 'JKEJ senarai semak LJUP (ujian)', 'items' => [
            ['code' => 'pelan_lampu', 'name' => 'Pelan Lampu Jalan', 'is_required' => true, 'precheck_keywords' => ['LAMPU']],
            ['code' => 'laporan_fotometrik', 'name' => 'Laporan Fotometrik', 'is_required' => true],
        ]];
    }

    private function seniorEngineer(): User
    {
        return User::factory()->create(['email' => 'jurutera@indecs.test', 'department_id' => 'JKEJ', 'role' => 'senior_engineering_officer']);
    }

    public function test_ljup_is_a_portal_type_owned_by_jkej(): void
    {
        $this->assertContains('LJUP', ApplicationType::query()->portal()->pluck('id')->all());
        $this->assertSame('JKEJ', ApplicationType::query()->findOrFail('LJUP')->primary_department_id);
    }

    public function test_senior_engineer_publishes_ljup_catalogue_from_a_file(): void
    {
        $this->seniorEngineer();
        $file = tempnam(sys_get_temp_dir(), 'ljup-catalogue-');
        file_put_contents($file, json_encode($this->catalogue(), JSON_THROW_ON_ERROR));

        $this->artisan('catalogue:publish', ['type' => 'LJUP', 'file' => $file, '--actor' => 'jurutera@indecs.test'])->assertSuccessful();

        $this->assertSame(1, (int) DB::table('core.document_catalogues')->where('application_type_id', 'LJUP')->value('version'));
        $this->assertSame(2, DB::table('document_types')->where('code', 'like', 'kej_ljup_%')->count());
    }

    public function test_building_officer_cannot_publish_ljup_catalogue(): void
    {
        $officer = User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']);
        $this->expectException(HttpException::class);
        app(DocumentCatalogueService::class)->publish('LJUP', $this->catalogue(), $officer);
    }

    public function test_ljup_draft_requires_lighting_types(): void
    {
        $owner = $this->applicant();
        $this->actingAs($owner)->post(route('portal.drafts.store'), ['application_type_id' => 'LJUP', 'title' => 'Lampu jalan Taman Uji'])
            ->assertSessionHasErrors('lighting_types');
        $this->assertSame(0, ApplicantDraft::query()->count());
    }

    public function test_ljup_draft_stores_lighting_types(): void
    {
        $owner = $this->applicant();
        $this->actingAs($owner)->post(route('portal.drafts.store'), ['application_type_id' => 'LJUP', 'title' => 'Lampu jalan Taman Uji',
            'lighting_types' => ['BESI', 'SOLAR']])->assertRedirect();
        $this->assertSame(['BESI', 'SOLAR'], ApplicantDraft::query()->sole()->lightingTypes());
    }

    public function test_ljup_draft_rejects_unknown_lighting_type(): void
    {
        $owner = $this->applicant();
        $this->actingAs($owner)->post(route('portal.drafts.store'), ['application_type_id' => 'LJUP', 'title' => 'Lampu',
            'lighting_types' => ['NEON']])->assertSessionHasErrors('lighting_types.0');
    }

    public function test_non_ljup_draft_ignores_lighting_types(): void
    {
        $owner = $this->applicant();
        $this->actingAs($owner)->post(route('portal.drafts.store'), ['application_type_id' => 'PKM', 'title' => 'Rumah',
            'lighting_types' => ['BESI']])->assertRedirect();
        $this->assertSame([], ApplicantDraft::query()->sole()->lightingTypes());
    }

    public function test_create_page_offers_lighting_types_revealed_when_ljup_is_chosen(): void
    {
        $owner = $this->applicant();
        $this->actingAs($owner)->get(route('portal.drafts.create'))->assertOk()
            ->assertSee('data-show-for-type="LJUP"', false)
            ->assertSee('name="lighting_types[]"', false)
            ->assertSee('id="jenis-lampu" hidden data-show-for-type', false);
    }

    public function test_edit_page_shows_lighting_type_choices_for_ljup(): void
    {
        $owner = $this->applicant();
        $this->actingAs($owner)->post(route('portal.drafts.store'), ['application_type_id' => 'LJUP', 'title' => 'Lampu', 'lighting_types' => ['BESI']]);
        $this->actingAs($owner)->get(route('portal.drafts.edit', ApplicantDraft::query()->sole()))->assertOk()
            ->assertSee('Jenis lampu')->assertSee('Lampu besi')->assertSee('name="lighting_types[]"', false);
    }
}
