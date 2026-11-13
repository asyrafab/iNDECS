<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Knowledge\LessonExporter;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\FindingFeedbackService;
use App\Airs\Review\ProfileFieldReviewService;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kejuruteraan\BuildsHandedOverLjup;
use Tests\TestCase;

final class LessonExportTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    private string $path;

    private Application $application;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'airs-lessons-'.uniqid();
        config(['airs.matrix.lessons_path' => $this->path]);
        [$this->application] = $this->paidLjup();
        $this->officer = $this->engineer();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->path);
        parent::tearDown();
    }

    private function manualFinding(): ReviewFinding
    {
        return ReviewFinding::query()->whereIn('status', ['PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI'])->firstOrFail();
    }

    private function lines(?string $file): array
    {
        return $file === null ? [] : array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), array_filter(explode("\n", (string) file_get_contents($file))));
    }

    public function test_export_writes_candidate_lessons_for_officer_corrections(): void
    {
        $finding = $this->manualFinding();
        app(FindingFeedbackService::class)->record($this->officer, $finding, 'BELUM_DITUNAIKAN', 'Jadual lampu tidak sepadan dengan pelan.');

        $result = app(LessonExporter::class)->export();

        $this->assertSame(1, $result['count']);
        $lesson = $this->lines($result['file'])[0];
        $this->assertSame('CANDIDATE_UNEVALUATED', $lesson['status']);
        $this->assertSame('finding_correction', $lesson['kind']);
        $this->assertSame($finding->rule_id, $lesson['rule_id']);
        $this->assertSame($finding->status->value, $lesson['ai_status']);
        $this->assertSame('BELUM_DITUNAIKAN', $lesson['officer_status']);
        $this->assertSame('Jadual lampu tidak sepadan dengan pelan.', $lesson['officer_comment']);
        $this->assertSame(64, strlen($lesson['case_key']));
    }

    public function test_matching_feedback_without_comment_is_not_exported(): void
    {
        $finding = $this->manualFinding();
        app(FindingFeedbackService::class)->record($this->officer, $finding, $finding->status->value, null);
        $this->assertSame(0, app(LessonExporter::class)->export()['count']);
    }

    public function test_export_is_incremental(): void
    {
        app(FindingFeedbackService::class)->record($this->officer, $this->manualFinding(), 'BELUM_DITUNAIKAN', 'Komen.');
        app(LessonExporter::class)->export();
        $second = app(LessonExporter::class)->export();
        $this->assertSame(0, $second['count']);
        $this->assertNull($second['file']);
        $this->assertCount(1, glob($this->path.DIRECTORY_SEPARATOR.'*.jsonl'));
    }

    public function test_export_contains_no_identifiers(): void
    {
        app(FindingFeedbackService::class)->record($this->officer, $this->manualFinding(), 'BELUM_DITUNAIKAN', 'Komen teknikal.');
        $text = (string) file_get_contents(app(LessonExporter::class)->export()['file']);
        $owner = User::query()->whereKey(\App\Models\ApplicantDraft::query()->value('user_id'))->firstOrFail();
        foreach ([$this->application->application_reference, $this->application->title, $owner->email, $owner->name, $this->officer->email, $this->officer->name, $this->application->id] as $identifier) {
            $this->assertStringNotContainsString((string) $identifier, $text);
        }
        foreach (ApplicationDocument::query()->get() as $document) {
            $this->assertStringNotContainsString($document->original_name, $text);
            $this->assertStringNotContainsString($document->storage_path, $text);
        }
    }

    public function test_field_corrections_are_exported(): void
    {
        $version = ReviewVersion::query()->sole();
        $profile = ApplicationProfile::query()->where('review_version_id', $version->id)->sole();
        $profile->forceFill(['profile_json' => array_merge($profile->profile_json ?? [], ['drawing_revision' => 'B'])])->save();
        app(ProfileFieldReviewService::class)->record($this->officer, $version, 'drawing_revision', 'CORRECTED', 'C', 'Semakan terkini pada tajuk lukisan.', 0);

        $lesson = $this->lines(app(LessonExporter::class)->export()['file'])[0];
        $this->assertSame('field_correction', $lesson['kind']);
        $this->assertSame('drawing_revision', $lesson['field']);
        $this->assertSame('B', $lesson['ai_value']);
        $this->assertSame('C', $lesson['officer_value']);
    }

    public function test_command_reports_the_file(): void
    {
        app(FindingFeedbackService::class)->record($this->officer, $this->manualFinding(), 'BELUM_DITUNAIKAN', 'Komen.');
        $this->artisan('airs:export-lessons')->expectsOutputToContain('1')->assertSuccessful();
    }
}
