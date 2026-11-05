<?php

namespace Tests\Feature\Checklist;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistRulesPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
    }

    public function test_admin_sees_condition_editor_with_config_options(): void
    {
        $this->actingAs($this->admin)->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertOk()
            ->assertSee('Keperluan dokumen')->assertSee('Syarat (pilihan)')
            ->assertSee('name="items[0][guidance]"', false)
            ->assertSee('name="items[0][conditions][work_types][]"', false)
            ->assertSee('name="items[0][conditions][storeys_min]"', false)
            ->assertSee('name="items[__INDEX__][conditions][site_conditions][]"', false)
            ->assertSee('Tambahan / pembesaran')->assertSee('Lereng bukit')->assertSee('Kediaman teres');
    }

    public function test_round_trip_publish_then_edit_shows_saved_conditions(): void
    {
        $this->actingAs($this->admin)->post(route('bangunan.document-catalogue.publish', 'TEL'), [
            'version' => 0, 'source_reference' => 'Senarai semak JBANG 2026',
            'items' => [
                ['code' => 'pelan_lantai', 'name' => 'Pelan lantai', 'is_required' => '1', 'guidance' => '', 'conditions' => ['storeys_min' => '']],
                ['code' => 'pelan_struktur', 'name' => 'Pelan struktur', 'is_required' => '1', 'guidance' => 'Dimeterai jurutera.',
                    'conditions' => ['work_types' => ['tambahan'], 'storeys_min' => '3', 'floor_area_min' => '']],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertOk()
            ->assertSee('Sentiasa diperlukan')
            ->assertSee('Wajib jika: Tambahan / pembesaran · 3 tingkat ke atas')
            ->assertSee('Dimeterai jurutera.')
            ->assertSee('name="items[1][conditions][storeys_min]" value="3"', false)
            ->assertSee('value="tambahan" checked', false);
    }

    public function test_senior_officer_gets_403_on_page(): void
    {
        $senior = User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']);
        $this->actingAs($senior)->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertForbidden();
    }
}
