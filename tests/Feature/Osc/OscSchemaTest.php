<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\Osc\TablingPurpose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
}
