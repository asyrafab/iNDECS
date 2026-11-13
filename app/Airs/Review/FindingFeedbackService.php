<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\FindingStatus;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewFindingFeedback;
use App\Enums\PaidCheckState;
use App\Models\AuditLog;
use App\Models\PaidCheckJourney;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Records an officer's attributed comment on an AIRS finding of the final, handed-over check. */
final class FindingFeedbackService
{
    public function record(User $officer, ReviewFinding $finding, string $status, ?string $comment): ReviewFindingFeedback
    {
        abort_unless($officer->canPerformLjup('review'), 403, 'Kebenaran semakan LJUP diperlukan.');
        $status = FindingStatus::tryFrom($status) ?? throw ValidationException::withMessages(['feedback' => 'Status tidak sah.']);

        return DB::transaction(function () use ($officer, $finding, $status, $comment): ReviewFindingFeedback {
            $version = $finding->reviewVersion()->with('reviewCase')->firstOrFail();
            $journey = PaidCheckJourney::query()->where('application_id', $version->reviewCase->application_id)->lockForUpdate()->first();
            if ($journey?->state !== PaidCheckState::HandedOver || (int) $journey->final_check_number !== (int) $version->check_number) {
                throw ValidationException::withMessages(['feedback' => 'Ulasan hanya untuk semakan muktamad yang telah diserahkan.']);
            }
            if ($status === FindingStatus::Complied && ! $finding->evidence()->exists()) {
                throw ValidationException::withMessages(['feedback' => 'Bukti diperlukan untuk status Ditunaikan.']);
            }
            $feedback = new ReviewFindingFeedback;
            $feedback->forceFill(['review_finding_id' => $finding->id, 'actor_id' => $officer->id, 'status' => $status,
                'comment' => filled($comment) ? mb_substr(trim((string) $comment), 0, 2000) : null])->save();
            AuditLog::query()->create(['auditable_type' => ReviewFinding::class, 'auditable_id' => $finding->id, 'event' => 'airs_finding_feedback',
                'actor_id' => $officer->id, 'before_values' => ['ai_status' => $finding->status->value],
                'after_values' => ['officer_status' => $status->value, 'feedback_id' => $feedback->id], 'ip_address' => request()?->ip()]);

            return $feedback;
        });
    }
}
