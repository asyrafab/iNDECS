<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\MeetingStatus;
use App\Enums\Osc\TablingPurpose;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\DepartmentSetting;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Notifications\Osc\AgendaPublished;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AgendaPublishingTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $chair;

    private User $planner;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->planner = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026',
            'scheduled_at' => now()->addWeek(),
            'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2),
            'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->chair->id,
            'secretary_id' => $this->secretary->id,
        ], $this->secretary);
    }

    private function service(): AgendaService
    {
        return app(AgendaService::class);
    }

    private function propose(string $title, string $department = 'JPBD'): AgendaItem
    {
        return $this->service()->proposeStandalone($this->meeting, [
            'standalone_type' => 'Penamaan Jalan & Taman', 'title' => $title, 'reference' => null,
            'purpose' => TablingPurpose::Permohonan, 'presenter_id' => $this->planner->id,
            'department_id' => $department,
        ], $this->secretary);
    }

    private function accepted(string $title): AgendaItem
    {
        $item = $this->propose($title);

        return $this->service()->accept($item, $item->row_version, $this->secretary);
    }

    private function version(): int
    {
        return $this->meeting->fresh()->row_version;
    }

    private function assertRefused(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame($message, collect($e->errors())->flatten()->first());
        }
    }

    public function test_accept_order_and_publish_creates_version_one(): void
    {
        Notification::fake();
        [$a, $b, $c] = [$this->accepted('A'), $this->accepted('B'), $this->accepted('C')];
        $this->assertSame([1, 2, 3], [$a->position, $b->position, $c->position]);
        $this->assertSame(AgendaItemStatus::Accepted, $a->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_item_accepted', 'auditable_id' => $a->id]);

        $this->service()->reorder($this->meeting, [$c->id, $a->id, $b->id], $this->version(), $this->secretary);
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_agenda_reordered', 'auditable_id' => $this->meeting->id]);
        $version = $this->service()->publish($this->meeting, $this->version(), $this->secretary);

        $meeting = $this->meeting->fresh();
        $this->assertSame(1, $version->version);
        $this->assertSame(1, $meeting->agenda_version);
        $this->assertSame(MeetingStatus::AgendaPublished, $meeting->status);
        $this->assertSame($c->id, $version->items[0]['id']);
        $this->assertSame([$c->id, $a->id, $b->id], array_column($version->items, 'id'));
        $this->assertSame($this->secretary->id, $version->published_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_agenda_published', 'auditable_id' => $this->meeting->id]);
        Notification::assertSentTo($this->chair, AgendaPublished::class);
        Notification::assertNotSentTo($this->secretary, AgendaPublished::class);
    }

    public function test_republish_records_changes(): void
    {
        [$a, $b] = [$this->accepted('A'), $this->accepted('B')];
        $this->service()->publish($this->meeting, $this->version(), $this->secretary);

        $this->service()->returnToDepartment($b->refresh(), $b->row_version, 'Kertas kerja tidak lengkap.', $this->secretary);
        $d = $this->accepted('D');
        $version = $this->service()->publish($this->meeting, $this->version(), $this->secretary);

        $this->assertSame(2, $version->version);
        $this->assertSame(['added' => [$d->id], 'removed' => [$b->id], 'reordered' => false], $version->changes);
        $this->assertSame([$a->id, $d->id], array_column($version->items, 'id'));
    }

    public function test_republish_flags_reordering(): void
    {
        [$a, $b] = [$this->accepted('A'), $this->accepted('B')];
        $this->service()->publish($this->meeting, $this->version(), $this->secretary);
        $this->service()->reorder($this->meeting, [$b->id, $a->id], $this->version(), $this->secretary);

        $version = $this->service()->publish($this->meeting, $this->version(), $this->secretary);

        $this->assertSame(['added' => [], 'removed' => [], 'reordered' => true], $version->changes);
    }

    public function test_concurrent_reorder_is_refused(): void
    {
        [$a, $b, $c] = [$this->accepted('A'), $this->accepted('B'), $this->accepted('C')];
        $expected = $this->version();

        $this->service()->reorder($this->meeting, [$c->id, $a->id, $b->id], $expected, $this->secretary);
        $this->assertRefused('Rekod telah berubah. Muat semula sebelum meneruskan.',
            fn () => $this->service()->reorder($this->meeting, [$a->id, $b->id, $c->id], $expected, $this->secretary));

        $this->assertSame([$c->id, $a->id, $b->id], $this->meeting->items()->pluck('id')->all());
        $this->assertSame([1, 2, 3], $this->meeting->items()->pluck('position')->all());
    }

    public function test_reorder_must_include_every_accepted_item(): void
    {
        [$a, $b] = [$this->accepted('A'), $this->accepted('B')];
        $proposed = $this->propose('P');
        $expected = $this->version();

        foreach ([[$a->id], [$a->id, $b->id, $proposed->id], [$a->id, $a->id], [$a->id, $b->id, $b->id]] as $ids) {
            $this->assertRefused('Susunan agenda tidak lengkap.', fn () => $this->service()->reorder($this->meeting, $ids, $expected, $this->secretary));
        }
        $this->assertSame($expected, $this->version());
    }

    public function test_endorsement_department_item_cannot_be_accepted_unendorsed(): void
    {
        DepartmentSetting::query()->create(['department_id' => 'JBANG', 'requires_endorsement' => true]);
        $item = $this->propose('Bangunan', 'JBANG');

        $this->assertRefused('Cadangan ini perlu disokong ketua jabatan terlebih dahulu.',
            fn () => $this->service()->accept($item, $item->row_version, $this->secretary));
        $this->assertSame(AgendaItemStatus::Proposed, $item->fresh()->status);

        $endorsed = $this->service()->endorse($item, $item->row_version, $this->chair);
        $accepted = $this->service()->accept($endorsed, $endorsed->row_version, $this->secretary);

        $this->assertSame(AgendaItemStatus::Accepted, $accepted->status);
        $this->assertSame(1, $accepted->position);
    }

    public function test_accept_is_manager_only_and_version_checked(): void
    {
        $item = $this->propose('A');

        try {
            $this->service()->accept($item, 1, $this->planner);
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertRefused('Rekod telah berubah. Muat semula sebelum meneruskan.', fn () => $this->service()->accept($item, 0, $this->secretary));
        $this->assertSame(AgendaItemStatus::Proposed, $item->fresh()->status);
    }

    public function test_publish_with_no_items_is_refused(): void
    {
        $this->propose('Belum diterima');

        $this->assertRefused('Agenda tiada perkara untuk diterbitkan.', fn () => $this->service()->publish($this->meeting, $this->version(), $this->secretary));
        $this->assertSame(0, $this->meeting->fresh()->agenda_version);
        $this->assertSame(MeetingStatus::Draft, $this->meeting->fresh()->status);
        $this->assertDatabaseCount('core.osc_agenda_versions', 0);
    }

    public function test_publish_refuses_stale_meeting_version(): void
    {
        $this->accepted('A');

        $this->assertRefused('Rekod telah berubah. Muat semula sebelum meneruskan.', fn () => $this->service()->publish($this->meeting, 99, $this->secretary));
    }

    public function test_return_requires_reason_and_removes_position(): void
    {
        $item = $this->accepted('A');

        foreach (['', '   '] as $reason) {
            $this->assertRefused('Sebab pengembalian diperlukan.', fn () => $this->service()->returnToDepartment($item, $item->row_version, $reason, $this->secretary));
        }

        $returned = $this->service()->returnToDepartment($item, $item->row_version, 'Sila lengkapkan kertas kerja.', $this->secretary);

        $this->assertSame(AgendaItemStatus::Returned, $returned->status);
        $this->assertNull($returned->position);
        $this->assertSame('Sila lengkapkan kertas kerja.', $returned->return_reason);
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_item_returned', 'auditable_id' => $item->id]);
    }

    public function test_notification_has_no_attachments(): void
    {
        $mail = (new AgendaPublished($this->meeting, 2, 3))->toMail($this->chair);

        $this->assertSame('Agenda Mesyuarat OSC Bil. 21/2026 diterbitkan (versi 3)', $mail->subject);
        $this->assertSame([], $mail->attachments);
        $this->assertSame([], $mail->rawAttachments);
        $this->assertSame(url('/osc/meetings/'.$this->meeting->id), $mail->actionUrl);
        $this->assertStringContainsString('2', implode(' ', $mail->introLines));
    }

    /** @return array<string, array{MeetingStatus}> */
    public static function lockedStatuses(): array
    {
        return ['closed' => [MeetingStatus::Closed], 'minutes_confirmed' => [MeetingStatus::MinutesConfirmed]];
    }

    #[DataProvider('lockedStatuses')]
    public function test_closed_meeting_refuses_all_agenda_changes(MeetingStatus $status): void
    {
        $a = $this->accepted('A');
        $b = $this->accepted('B');
        $p = $this->propose('P');
        $this->meeting->update(['status' => $status]);
        $version = $this->version();
        $message = 'Agenda mesyuarat ini tidak lagi boleh diubah.';

        $this->assertRefused($message, fn () => $this->service()->accept($p, $p->row_version, $this->secretary));
        $this->assertRefused($message, fn () => $this->service()->returnToDepartment($a, $a->row_version, 'Sebab', $this->secretary));
        $this->assertRefused($message, fn () => $this->service()->reorder($this->meeting, [$b->id, $a->id], $version, $this->secretary));
        $this->assertRefused($message, fn () => $this->service()->publish($this->meeting, $version, $this->secretary));

        $this->assertSame(AgendaItemStatus::Proposed, $p->fresh()->status);
        $this->assertSame([1, 2], [$a->fresh()->position, $b->fresh()->position]);
        $this->assertSame(AgendaItemStatus::Accepted, $a->fresh()->status);
        $this->assertSame($version, $this->version());
        $this->assertSame(0, $this->meeting->fresh()->agenda_version);
        $this->assertDatabaseCount('core.osc_agenda_versions', 0);
    }

    public function test_in_progress_meeting_still_allows_accept(): void
    {
        $item = $this->propose('A');
        $this->meeting->update(['status' => MeetingStatus::InProgress]);

        $this->assertSame(AgendaItemStatus::Accepted, $this->service()->accept($item, $item->row_version, $this->secretary)->status);
    }
}
