<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\Osc\TablingPurpose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class OscSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_osc_tables_permissions_and_roles_exist(): void
    {
        $this->seed();
        foreach (['osc_meetings', 'osc_meeting_members', 'osc_agenda_items', 'osc_agenda_item_papers', 'osc_agenda_versions', 'osc_department_settings'] as $table) {
            $this->assertTrue(Schema::hasTable('core.'.$table), $table);
        }
        foreach (['osc.meeting.view', 'osc.meeting.manage', 'osc.agenda.propose', 'osc.agenda.endorse'] as $permission) {
            $this->assertDatabaseHas('core.permissions', ['name' => $permission]);
        }
        $secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->assertTrue($secretary->canPerformOsc('osc.meeting.manage'));
        $officer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->assertTrue($officer->canPerformOsc('osc.agenda.propose'));
        $this->assertFalse($officer->canPerformOsc('osc.meeting.manage'));
        $this->assertSame('Pertimbangan semula', TablingPurpose::PertimbanganSemula->label());
    }

    public function test_migration_down_drops_empty_tables_and_refuses_when_meetings_exist(): void
    {
        $migration = require database_path('migrations/2026_10_29_000000_create_osc_meetings_and_agenda.php');

        DB::table('core.osc_meetings')->insert([
            'id' => (string) Str::ulid(), 'number' => '1/2026', 'scheduled_at' => now(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now(), 'paper_cutoff_at' => now(),
            'created_by' => User::factory()->create()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $migration->down();
            $this->fail('down() must refuse while meetings exist.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OSC meetings exist', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('core.osc_meetings'));

        DB::table('core.osc_meetings')->delete();
        $migration->down();
        foreach (['osc_meetings', 'osc_meeting_members', 'osc_agenda_items', 'osc_agenda_item_papers', 'osc_agenda_versions', 'osc_department_settings'] as $table) {
            $this->assertFalse(Schema::hasTable('core.'.$table), $table);
        }
    }
}
