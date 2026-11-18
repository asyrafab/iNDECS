<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Ai\FakeAiProvider;
use App\Airs\Contracts\AiProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class ModuleFoundationTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_airs_schema_tables_exist(): void
    {
        foreach (['review_cases', 'review_versions', 'review_files', 'review_pages', 'page_regions', 'document_classifications',
            'application_profiles', 'review_rules', 'review_findings', 'review_evidence', 'ai_runs', 'processing_tasks',
            'generated_reports', 'system_config_snapshots'] as $table) {
            $this->assertTrue(Schema::hasTable("airs.$table"), $table);
        }
        $this->assertFalse(Schema::hasColumn('airs.review_cases', 'billing_required'));
        $this->assertTrue(Schema::hasColumn('airs.review_files', 'source_document_id'));
    }

    public function test_configured_provider_is_fake_in_tests(): void
    {
        $this->assertSame('fake', config('airs.provider'));
        $this->assertFalse(config('airs.matrix.enabled'));
        $this->assertInstanceOf(FakeAiProvider::class, app(AiProvider::class));
    }

    public function test_case_belongs_to_application_and_tracks_latest_check(): void
    {
        $case = $this->airsCase();
        $this->airsVersion($case, 1);
        $two = $this->airsVersion($case, 2);
        $this->assertSame($case->id, $case->application->airsCase->id);
        $this->assertSame($two->id, $case->fresh()->latestVersion->id);
    }

    public function test_version_check_number_is_limited_to_two(): void
    {
        $this->expectException(QueryException::class);
        $this->airsVersion($this->airsCase(), 3);
    }

    public function test_one_case_per_application(): void
    {
        $case = $this->airsCase();
        $this->expectException(QueryException::class);
        $this->airsCase($case->application);
    }
}
