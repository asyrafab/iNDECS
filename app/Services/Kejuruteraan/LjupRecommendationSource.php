<?php

declare(strict_types=1);

namespace App\Services\Kejuruteraan;

use App\Airs\Models\ProfileFieldReview;
use App\Airs\Models\ReviewFindingFeedback;
use App\Airs\Models\ReviewVersion;
use App\Contracts\RecommendationSource;
use App\Enums\PaidCheckState;
use App\Models\AcceptedSubmission;
use App\Models\Application;
use App\Models\PaidCheckJourney;
use App\Models\User;

final class LjupRecommendationSource implements RecommendationSource
{
    public function outcomes(): array
    {
        return ['approve' => 'Lulus', 'amend' => 'Perlu pindaan', 'reject' => 'Tolak'];
    }

    public function lock(User $actor, string $applicationId): array
    {
        abort_unless($actor->canPerformLjup('access') && $actor->canPerformLjup('review'), 403);
        Application::query()->visibleToLjupOfficer($actor)->findOrFail($applicationId);
        $hint = PaidCheckJourney::query()->where('application_id', $applicationId)->first();
        abort_unless($hint?->state === PaidCheckState::HandedOver, 409, 'Syor memerlukan penyerahan muktamad kepada JKEJ.');
        // Profile reviews lock the version; finding feedback locks the journey. Take both before snapshotting.
        $version = ReviewVersion::query()->whereHas('reviewCase', fn ($q) => $q->where('application_id', $applicationId))
            ->where('check_number', $hint->final_check_number)->lockForUpdate()->first();
        $journey = PaidCheckJourney::query()->whereKey($hint->id)->lockForUpdate()->firstOrFail();
        $application = Application::query()->visibleToLjupOfficer($actor)->lockForUpdate()->findOrFail($applicationId);
        abort_unless($version?->status === 'COMPLETED' && $journey->state === PaidCheckState::HandedOver
            && $version->check_number === $journey->final_check_number && $application->responsible_department_id === 'JKEJ', 409);
        $accepted = AcceptedSubmission::query()->where('application_id', $applicationId)->latest('accepted_at')->orderByDesc('id')->first();
        abort_unless($accepted && hash_equals($accepted->document_set_hash, $version->document_set_hash), 409, 'Bukti penerimaan tidak sepadan dengan semakan muktamad.');

        return [
            'application_id' => $application->id, 'application_type' => 'LJUP',
            'application_reference' => $application->application_reference, 'title' => $application->title,
            'row_version' => $application->row_version, 'stage' => $application->current_stage->value, 'status' => $application->current_status->value,
            'accepted_submission_id' => $accepted->id, 'document_set_hash' => $accepted->document_set_hash,
            'document_manifest' => $accepted->document_manifest, 'missing_required_documents' => $accepted->missing_required_documents ?? [],
            'review_version_id' => $version->id, 'check_number' => $version->check_number,
            'finding_feedback' => ReviewFindingFeedback::query()->whereHas('finding', fn ($q) => $q->where('review_version_id', $version->id))->orderBy('id')->get()->toArray(),
            'profile_reviews' => ProfileFieldReview::query()->where('review_version_id', $version->id)->orderBy('id')->get()->toArray(),
        ];
    }
}
