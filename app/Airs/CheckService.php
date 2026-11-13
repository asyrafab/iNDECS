<?php

declare(strict_types=1);

namespace App\Airs;

use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Exceptions\CheckInProgress;
use App\Airs\Exceptions\CheckLimitReached;
use App\Airs\Exceptions\CheckNotAllowed;
use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFile;
use App\Airs\Models\ReviewVersion;
use App\Airs\Models\SystemConfigSnapshot;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The only entry point for paid AIRS checks. An application gets at most two completed
 * checks; a failed check never counts and is rerun under the same check number.
 */
final class CheckService
{
    public const MAX_COMPLETED_CHECKS = 2;

    private const ACTIVE_STATUSES = ['UPLOADED', 'PROCESSING'];

    /** @param Collection<int, ApplicationDocument> $documents */
    public function start(Application $application, CheckClearance $clearance, array $lightingTypes, Collection $documents): ReviewVersion
    {
        $module = config('airs.modules.'.$application->application_type_id);
        if (! is_array($module) || ($module['enabled'] ?? false) !== true) {
            throw new CheckNotAllowed('MODULE_NOT_ENABLED');
        }
        $lightingTypes = array_values(array_unique(array_map('strval', $lightingTypes)));
        if ($lightingTypes === [] || array_diff($lightingTypes, array_keys((array) config('airs.lighting_types'))) !== []) {
            throw new CheckNotAllowed('LIGHTING_TYPES_REQUIRED');
        }

        $version = DB::transaction(function () use ($application, $clearance, $lightingTypes, $documents, $module): ReviewVersion {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['airs:'.$application->id]);

            $case = ReviewCase::query()->where('application_id', $application->id)->lockForUpdate()->first();
            $latest = $case?->latestVersion()->first();
            if ($latest !== null && in_array($latest->status, self::ACTIVE_STATUSES, true)) {
                throw new CheckInProgress('A check is already queued or running for this application.');
            }
            $completed = $case === null ? 0 : $case->versions()->where('status', 'COMPLETED')->count();
            if ($completed >= self::MAX_COMPLETED_CHECKS) {
                throw new CheckLimitReached('This application has used both paid checks.');
            }

            $manifest = $this->verifiedManifest($application, $documents);
            $checkNumber = $completed + 1;

            $case ??= new ReviewCase(['application_id' => $application->id]);
            $case->forceFill([
                'review_module_code' => $module['code'],
                'selected_lighting_types' => $lightingTypes,
                'document_set_version' => $checkNumber,
                'clearance_kind' => $clearance->kind,
                'payment_clearance_reference' => $clearance->reference,
                'payment_cleared_at' => $clearance->clearedAt,
                'status' => ReviewCaseStatus::Uploaded,
                'progress' => 0,
                'current_step' => 'Check '.$checkNumber.' received',
                'error_code' => null,
                'error_message' => null,
                'warnings' => null,
                'file_count' => $documents->count(),
                'page_count' => 0,
                'total_original_bytes' => (int) $documents->sum('size_bytes'),
                'total_extracted_bytes' => 0,
                'completed_at' => null,
            ])->save();

            // A failed attempt at this check number is replaced: failures never consume a check.
            $failed = $case->versions()->where('check_number', $checkNumber)->where('status', 'FAILED')->first();
            if ($failed !== null) {
                $case->files()->where('submission_number', $checkNumber)->delete();
                $failed->findings()->delete();
                $failed->classifications()->delete();
                $failed->profile()->delete();
                $failed->reports()->delete();
                $failed->hasOne(SystemConfigSnapshot::class)->delete();
            }

            foreach ($documents as $document) {
                $this->snapshot($case, $document, $checkNumber);
            }

            $attributes = [
                'document_set_hash' => DocumentManifest::hash($manifest),
                'application_version' => config('airs.application_version'),
                'rule_pack' => $module['rule_pack'],
                'rule_version' => config('airs.rule_version'),
                'prompt_version' => config('airs.prompt_version'),
                'report_version' => config('airs.report_version'),
                'ai_provider' => config('airs.provider'),
                'text_model' => config('airs.text_model'),
                'vision_model' => config('airs.vision_model'),
                'status' => 'UPLOADED',
                'attempts' => 0,
                'overall_state' => null,
                'score' => null,
                'shadow_outcome' => null,
                'started_at' => null,
                'completed_at' => null,
            ];
            $version = $failed ?? new ReviewVersion(['review_case_id' => $case->id, 'check_number' => $checkNumber]);
            $version->forceFill($attributes)->save();

            SystemConfigSnapshot::create(['review_version_id' => $version->id, 'config_json' => [
                'limits' => config('airs.limits'),
                'application_version' => config('airs.application_version'),
                'provider' => config('airs.provider'),
                'models' => [config('airs.text_model'), config('airs.vision_model')],
                'rule_pack' => $module['rule_pack'],
                'rule_version' => config('airs.rule_version'),
                'prompt_version' => config('airs.prompt_version'),
                'score_threshold' => config('airs.score_threshold'),
                'selected_lighting_types' => $lightingTypes,
                'document_manifest' => $manifest,
            ]]);

            return $version;
        });

        $this->dispatch($version);

        return $version->fresh();
    }

    /** Staff or system rerun of a failed check with the same documents; never consumes a check. */
    public function rerun(ReviewVersion $failed): ReviewVersion
    {
        $version = DB::transaction(function () use ($failed): ReviewVersion {
            $applicationId = $failed->reviewCase()->value('application_id');
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['airs:'.$applicationId]); // same lock as start()
            $version = ReviewVersion::query()->lockForUpdate()->findOrFail($failed->id);
            if ($version->status !== 'FAILED') {
                throw new CheckNotAllowed('NOT_FAILED');
            }
            $version->forceFill(['status' => 'UPLOADED', 'started_at' => null, 'completed_at' => null])->save();
            $version->reviewCase->forceFill(['status' => ReviewCaseStatus::Uploaded, 'progress' => 0, 'error_code' => null,
                'error_message' => null, 'completed_at' => null, 'current_step' => 'Check '.$version->check_number.' rerun'])->save();

            return $version;
        });
        $this->dispatch($version);

        return $version->fresh();
    }

    public function completedChecks(Application $application): int
    {
        return ReviewVersion::query()->where('status', 'COMPLETED')
            ->whereHas('reviewCase', fn ($query) => $query->where('application_id', $application->id))->count();
    }

    private function dispatch(ReviewVersion $version): void
    {
        RunAirsCheck::dispatch($version->id); // connection and queue 'airs' are set by the job
    }

    /** @param Collection<int, ApplicationDocument> $documents */
    private function verifiedManifest(Application $application, Collection $documents): array
    {
        if ($documents->isEmpty()) {
            throw new CheckNotAllowed('DOCUMENT_INTEGRITY');
        }
        $disk = Storage::disk('local');

        return $documents->map(function (ApplicationDocument $document) use ($application, $disk): array {
            $intact = $document->application_id === $application->id && $document->is_current && $document->scan_status === 'clean'
                && $disk->exists($document->storage_path) && $disk->size($document->storage_path) === (int) $document->size_bytes
                && hash('sha256', (string) $disk->get($document->storage_path)) === $document->checksum_sha256;
            if (! $intact) {
                throw new CheckNotAllowed('DOCUMENT_INTEGRITY');
            }

            return ['id' => $document->id, 'revision' => $document->revision_number, 'sha256' => $document->checksum_sha256,
                'size_bytes' => (int) $document->size_bytes, 'name' => $document->original_name, 'mime_type' => $document->mime_type];
        })->values()->all();
    }

    private function snapshot(ReviewCase $case, ApplicationDocument $document, int $checkNumber): ReviewFile
    {
        $extension = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION)) ?: 'bin';
        $path = "airs/{$case->id}/{$checkNumber}/originals/{$document->id}.{$extension}";
        $disk = Storage::disk('local');
        $disk->put($path, (string) $disk->get($document->storage_path));

        return ReviewFile::create([
            'review_case_id' => $case->id,
            'source_document_id' => $document->id,
            'submission_number' => $checkNumber,
            'original_name' => $document->original_name,
            'safe_storage_path' => $path,
            'mime_type' => $document->mime_type,
            'extension' => $extension,
            'size_bytes' => (int) $document->size_bytes,
            'sha256' => $document->checksum_sha256,
            'processing_status' => $extension === 'zip' ? 'ORIGINAL_ARCHIVE' : 'PENDING',
        ]);
    }
}
