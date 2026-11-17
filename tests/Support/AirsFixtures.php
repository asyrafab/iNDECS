<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewVersion;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationType;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Shared fixtures for AIRS module tests. Expects RefreshDatabase, seeded data and Storage::fake('local'). */
trait AirsFixtures
{
    protected function ljupApplication(string $type = 'LJUP'): Application
    {
        if ($type === 'LJUP') {
            ApplicationType::firstOrCreate(['id' => 'LJUP'], ['name' => 'Pelan Lampu Jalan Untuk Pembangunan', 'primary_department_id' => 'JKEJ', 'is_active' => true]);
        }

        return Application::create(['application_type_id' => $type, 'application_reference' => $type.'/2026/'.Str::upper(Str::random(6)),
            'title' => 'AIRS fixture', 'submission_date' => today(), 'submission_year' => 2026,
            'responsible_department_id' => $type === 'LJUP' ? 'JKEJ' : 'JBANG',
            'current_stage' => ApplicationStage::DocumentVerification, 'current_status' => ApplicationStatus::InReview]);
    }

    protected function ljupDocument(Application $application, string $bytes, string $name = 'plan.pdf', string $mime = 'application/pdf'): ApplicationDocument
    {
        $type = DocumentType::firstOrCreate(['code' => 'AIRS_TEST'], ['name' => 'Test plan', 'allowed_extensions' => 'pdf,zip,png,jpg', 'max_file_size_kb' => 51200, 'is_active' => true]);
        $path = 'fixture/'.Str::ulid().'/'.$name;
        Storage::disk('local')->put($path, $bytes);
        $uploader = User::query()->first() ?? User::factory()->create();

        return ApplicationDocument::create(['application_id' => $application->id, 'document_type_id' => $type->id,
            'original_name' => $name, 'storage_path' => $path, 'mime_type' => $mime,
            'revision_number' => 1 + (int) ApplicationDocument::query()->where('application_id', $application->id)->where('document_type_id', $type->id)->max('revision_number'),
            'size_bytes' => strlen($bytes), 'checksum_sha256' => hash('sha256', $bytes), 'scan_status' => 'clean',
            'scanned_at' => now(), 'uploaded_by' => $uploader->id, 'uploaded_at' => now(), 'is_current' => true]);
    }

    protected function airsCase(?Application $application = null, array $attributes = []): ReviewCase
    {
        $application ??= $this->ljupApplication();

        return ReviewCase::create($attributes + ['application_id' => $application->id, 'review_module_code' => 'STREET_LIGHTING',
            'selected_lighting_types' => ['BESI'], 'status' => 'UPLOADED', 'clearance_kind' => 'paid',
            'payment_clearance_reference' => 'TEST-'.Str::ulid(), 'payment_cleared_at' => now()->subMinute()]);
    }

    protected function airsVersion(ReviewCase $case, int $checkNumber = 1, array $attributes = []): ReviewVersion
    {
        return ReviewVersion::create($attributes + ['review_case_id' => $case->id, 'check_number' => $checkNumber,
            'rule_pack' => 'ljup', 'rule_version' => 'v2', 'prompt_version' => 'v1', 'report_version' => 'v1', 'ai_provider' => 'fake']);
    }
}
