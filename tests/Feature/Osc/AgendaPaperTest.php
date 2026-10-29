<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Contracts\DocumentScanner;
use App\Enums\Osc\TablingPurpose;
use App\Models\AuditLog;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\AgendaItemPaper;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Services\Osc\AgendaPaperService;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AgendaPaperTest extends TestCase
{
    use RefreshDatabase;

    private const BAD_FILE = 'Kertas kerja mestilah fail PDF tidak melebihi 50 MB.';

    private User $secretary;

    private User $chair;

    private User $planner;

    private Meeting $meeting;

    private AgendaItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->planner = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026', 'scheduled_at' => now()->addWeek(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2), 'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
        ], $this->secretary);
        $this->item = app(AgendaService::class)->proposeStandalone($this->meeting, [
            'standalone_type' => 'Penamaan Jalan & Taman', 'title' => 'A', 'reference' => null,
            'purpose' => TablingPurpose::Permohonan, 'presenter_id' => $this->planner->id, 'department_id' => 'JPBD',
        ], $this->secretary);
        $this->scanner(null);
    }

    private function scanner(?string $failure): void
    {
        $this->app->instance(DocumentScanner::class, new class($failure) implements DocumentScanner
        {
            public function __construct(private readonly ?string $failure) {}

            public function assertClean(string $path): void
            {
                if ($this->failure !== null) {
                    throw new \RuntimeException($this->failure);
                }
            }
        });
    }

    private function pdf(string $name = 'kertas.pdf', string $body = 'one'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n%".$body."\ntrailer<<>>\n%%EOF\n");
    }

    private function upload(?UploadedFile $file = null, ?User $actor = null): AgendaItemPaper
    {
        return app(AgendaPaperService::class)->upload($this->item, $file ?? $this->pdf(), $actor ?? $this->planner);
    }

    private function message(callable $action): string
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return (string) collect($e->errors())->flatten()->first();
        }
        $this->fail('Expected ValidationException.');
    }

    public function test_presenter_uploads_versioned_scanned_pdf(): void
    {
        $first = $this->upload($this->pdf('a.pdf', 'one'));
        $second = $this->upload($this->pdf('b.pdf', 'two'));

        $this->assertSame([1, 2], [$first->version, $second->version]);
        $this->assertSame(hash('sha256', file_get_contents(Storage::disk('local')->path($second->path))), $second->sha256);
        $this->assertSame('osc/papers/'.$this->meeting->id.'/'.$this->item->id.'/v2.pdf', $second->path);
        Storage::disk('local')->assertExists($first->path);
        $this->assertSame([2, 1], $this->item->papers()->pluck('version')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_paper_uploaded', 'auditable_id' => $this->item->id, 'actor_id' => $this->planner->id]);
        $audit = AuditLog::query()->where('event', 'osc_paper_uploaded')->latest('id')->firstOrFail();
        $this->assertSame($second->sha256, $audit->after_values['sha256']);
    }

    public function test_proposer_and_manager_may_upload(): void
    {
        $this->assertSame($this->secretary->id, $this->item->proposed_by);
        $this->assertSame(1, $this->upload(null, $this->secretary)->version);
    }

    public function test_infected_or_unscannable_paper_is_refused_and_removed(): void
    {
        $this->scanner('infected');

        $this->assertSame('Dokumen gagal imbasan keselamatan dan tidak diterima.', $this->message(fn () => $this->upload()));
        $this->assertDatabaseCount('core.osc_agenda_item_papers', 0);
        Storage::disk('local')->assertDirectoryEmpty('osc/papers');
        $this->assertDatabaseMissing('audit_logs', ['event' => 'osc_paper_uploaded']);
    }

    public function test_scan_rejection_is_logged_with_the_scanner_message(): void
    {
        $this->scanner('Win.Test.EICAR_HDB-1 FOUND');
        Log::spy();

        $this->assertSame('Dokumen gagal imbasan keselamatan dan tidak diterima.', $this->message(fn () => $this->upload()));

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'eOSC+ agenda paper rejected by security scan.'
            && $context['item_id'] === $this->item->id && $context['actor_id'] === $this->planner->id
            && $context['error'] === 'Win.Test.EICAR_HDB-1 FOUND');
        $this->assertDatabaseCount('core.osc_agenda_item_papers', 0);
        Storage::disk('local')->assertDirectoryEmpty('osc/papers');
    }

    public function test_integrity_failures_at_download_are_logged_as_critical(): void
    {
        $paper = $this->upload();
        $service = app(AgendaPaperService::class);
        Log::spy();

        Storage::disk('local')->put($paper->path, 'tampered');
        $this->assertDownloadStatus(409, fn () => $service->download($paper, $this->secretary));
        Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context): bool => $message === 'eOSC+ agenda paper failed its SHA-256 check.'
            && $context['paper_id'] === $paper->id && $context['item_id'] === $this->item->id && $context['actor_id'] === $this->secretary->id);

        Storage::disk('local')->delete($paper->path);
        $this->assertDownloadStatus(409, fn () => $service->download($paper, $this->secretary));
        Log::shouldHaveReceived('critical')->withArgs(fn (string $message, array $context): bool => $message === 'eOSC+ agenda paper file is missing.'
            && $context['paper_id'] === $paper->id && $context['item_id'] === $this->item->id && $context['actor_id'] === $this->secretary->id);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'osc_paper_viewed']);
    }

    private function assertDownloadStatus(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected an HTTP exception.');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_non_pdf_is_refused(): void
    {
        $fake = UploadedFile::fake()->createWithContent('palsu.pdf', 'MZ not really a pdf');
        $this->assertSame(self::BAD_FILE, $this->message(fn () => $this->upload($fake)));

        $big = UploadedFile::fake()->create('besar.pdf', 51 * 1024, 'application/pdf');
        $this->assertSame(self::BAD_FILE, $this->message(fn () => $this->upload($big)));
        $this->assertDatabaseCount('core.osc_agenda_item_papers', 0);
    }

    public function test_other_officer_cannot_upload(): void
    {
        $other = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);

        try {
            $this->upload(null, $other);
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('core.osc_agenda_item_papers', 0);
    }

    public function test_download_audited_and_checksum_verified(): void
    {
        $paper = $this->upload();
        $service = app(AgendaPaperService::class);

        try {
            $service->download($paper, $this->chair);
            $this->fail('Draft meeting is not visible to a member.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseMissing('audit_logs', ['event' => 'osc_paper_viewed']);

        $this->item = app(AgendaService::class)->accept($this->item, $this->item->row_version, $this->secretary);
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);

        $this->assertSame(200, $service->download($paper, $this->chair)->getStatusCode());
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_paper_viewed', 'auditable_id' => $this->item->id, 'actor_id' => $this->chair->id]);
        $this->assertSame(1, AuditLog::query()->where('event', 'osc_paper_viewed')->count());
        $this->assertSame(200, $service->download($paper, $this->planner)->getStatusCode());

        Storage::disk('local')->put($paper->path, 'tampered');
        try {
            $service->download($paper, $this->chair);
            $this->fail('Expected a 409.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(2, AuditLog::query()->where('event', 'osc_paper_viewed')->count());
    }

    public function test_manager_downloads_draft_meeting_paper(): void
    {
        $paper = $this->upload();

        $this->assertSame(200, app(AgendaPaperService::class)->download($paper, $this->secretary)->getStatusCode());
    }

    public function test_missing_paper_flag_after_cutoff(): void
    {
        $this->assertFalse($this->item->fresh()->isMissingPaper());

        $this->meeting->update(['paper_cutoff_at' => now()->subHour()]);
        $this->assertTrue($this->item->fresh()->isMissingPaper());

        $this->upload();
        $this->assertFalse($this->item->fresh()->isMissingPaper());
    }
}
