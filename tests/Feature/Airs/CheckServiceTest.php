<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\CheckClearance;
use App\Airs\CheckService;
use App\Airs\Exceptions\CheckInProgress;
use App\Airs\Exceptions\CheckLimitReached;
use App\Airs\Exceptions\CheckNotAllowed;
use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\ReviewFile;
use App\Airs\Models\ReviewVersion;
use App\Models\Application;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class CheckServiceTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Queue::fake();
        $this->application = $this->ljupApplication();
        $this->ljupDocument($this->application, $this->pdf('one'), 'pelan-lampu-jalan.pdf');
    }

    private function pdf(string $marker): string
    {
        return "%PDF-1.4\n% {$marker}\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
    }

    private function clearance(): CheckClearance
    {
        return new CheckClearance('paid', 'TEST-PAY-001', CarbonImmutable::now()->subMinute());
    }

    private function start(?Application $application = null, array $types = ['BESI']): ReviewVersion
    {
        $application ??= $this->application;

        return app(CheckService::class)->start($application, $this->clearance(), $types, $application->currentDocuments()->get());
    }

    private function exceptionCode(callable $callback): string
    {
        try {
            $callback();
        } catch (CheckNotAllowed $exception) {
            return $exception->getMessage();
        }
        $this->fail('Expected CheckNotAllowed');
    }

    public function test_first_start_creates_check_one_and_dispatches_job(): void
    {
        $version = $this->start();

        $this->assertSame(1, $version->check_number);
        $this->assertSame('STREET_LIGHTING', $version->reviewCase->review_module_code);
        $this->assertSame('TEST-PAY-001', $version->reviewCase->payment_clearance_reference);
        $this->assertSame(64, strlen((string) $version->document_set_hash));
        $file = ReviewFile::query()->where('review_case_id', $version->review_case_id)->sole();
        $this->assertSame(1, (int) $file->submission_number);
        $this->assertNotNull($file->source_document_id);
        Storage::disk('local')->assertExists($file->safe_storage_path);
        Queue::assertPushedOn('airs', RunAirsCheck::class, fn ($job) => $job->reviewVersionId === $version->id);
    }

    public function test_refuses_application_type_without_enabled_module(): void
    {
        $pbk = $this->ljupApplication('PBK');
        $this->ljupDocument($pbk, $this->pdf('pbk'));
        $this->assertSame('MODULE_NOT_ENABLED', $this->exceptionCode(fn () => $this->start($pbk)));
    }

    public function test_refuses_missing_lighting_types(): void
    {
        $this->assertSame('LIGHTING_TYPES_REQUIRED', $this->exceptionCode(fn () => $this->start(null, [])));
        $this->assertSame('LIGHTING_TYPES_REQUIRED', $this->exceptionCode(fn () => $this->start(null, ['NEON'])));
    }

    public function test_refuses_document_whose_bytes_changed(): void
    {
        $document = $this->application->currentDocuments()->first();
        Storage::disk('local')->put($document->storage_path, $this->pdf('tampered'));
        $this->assertSame('DOCUMENT_INTEGRITY', $this->exceptionCode(fn () => $this->start()));
        $this->assertDatabaseCount('airs.review_versions', 0);
    }

    public function test_refuses_document_from_another_application(): void
    {
        $other = $this->ljupApplication();
        $foreign = $this->ljupDocument($other, $this->pdf('foreign'));
        $this->assertSame('DOCUMENT_INTEGRITY', $this->exceptionCode(fn () => app(CheckService::class)
            ->start($this->application, $this->clearance(), ['BESI'], collect([$foreign]))));
    }

    public function test_refuses_empty_document_set(): void
    {
        $this->assertSame('DOCUMENT_INTEGRITY', $this->exceptionCode(fn () => app(CheckService::class)
            ->start($this->application, $this->clearance(), ['BESI'], collect())));
    }

    public function test_refuses_second_start_while_check_in_progress(): void
    {
        $this->start();
        try {
            $this->start();
            $this->fail('Expected CheckInProgress');
        } catch (CheckInProgress) {
            $this->assertDatabaseCount('airs.review_versions', 1);
        }
    }

    public function test_second_start_after_completion_creates_check_two(): void
    {
        $this->start()->forceFill(['status' => 'COMPLETED'])->save();
        $this->ljupDocument($this->application, $this->pdf('amended'), 'pelan-pindaan.pdf');

        $two = $this->start();

        $this->assertSame(2, $two->check_number);
        $this->assertSame(2, (int) $two->reviewCase->document_set_version);
        $this->assertSame(2, ReviewFile::query()->where('review_case_id', $two->review_case_id)->where('submission_number', 2)->count());
    }

    public function test_third_check_is_refused(): void
    {
        $this->start()->forceFill(['status' => 'COMPLETED'])->save();
        $this->start()->forceFill(['status' => 'COMPLETED'])->save();
        $this->expectException(CheckLimitReached::class);
        $this->start();
    }

    public function test_failed_check_is_rerun_without_consuming_a_check(): void
    {
        $failed = $this->start();
        $failed->forceFill(['status' => 'FAILED'])->save();
        $this->assertSame(0, app(CheckService::class)->completedChecks($this->application));

        $rerun = app(CheckService::class)->rerun($failed->fresh());

        $this->assertSame($failed->id, $rerun->id);
        $this->assertSame(1, $rerun->check_number);
        $this->assertSame('UPLOADED', $rerun->status);
        $this->assertDatabaseCount('airs.review_versions', 1);
        Queue::assertPushed(RunAirsCheck::class, 2);
    }

    public function test_start_after_failure_reruns_same_check_instead_of_new_number(): void
    {
        $this->start()->forceFill(['status' => 'FAILED'])->save();
        $again = $this->start();
        $this->assertSame(1, $again->check_number);
        $this->assertDatabaseCount('airs.review_versions', 1);
    }

    public function test_rerun_refuses_a_check_that_did_not_fail(): void
    {
        $version = $this->start();
        $this->expectException(CheckNotAllowed::class);
        app(CheckService::class)->rerun($version);
    }

    public function test_clearance_rejects_unknown_kind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CheckClearance('free', 'X', CarbonImmutable::now());
    }

    public function test_clearance_rejects_blank_reference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CheckClearance('paid', '  ', CarbonImmutable::now());
    }
}
