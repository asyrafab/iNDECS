<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Models\ProfileFieldReview;
use App\Airs\Models\ReviewVersion;
use App\Enums\PaidCheckState;
use App\Models\AuditLog;
use App\Models\PaidCheckJourney;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Officer disposition on a MATRIX-extracted profile field of the final, handed-over check
 * (rules ported from the archived AIRS MatrixReviewController). Never changes official particulars.
 */
final class ProfileFieldReviewService
{
    public function record(User $officer, ReviewVersion $version, string $field, string $disposition, ?string $value, string $reason, int $expectedRevision): ProfileFieldReview
    {
        abort_unless($officer->canPerformLjup('review'), 403, 'Kebenaran semakan LJUP diperlukan.');

        return DB::transaction(function () use ($officer, $version, $field, $disposition, $value, $reason, $expectedRevision): ProfileFieldReview {
            $locked = ReviewVersion::query()->with(['profile', 'reviewCase'])->lockForUpdate()->findOrFail($version->id);
            $journey = PaidCheckJourney::query()->where('application_id', $locked->reviewCase->application_id)->first();
            abort_unless($locked->status === 'COMPLETED' && $journey?->state === PaidCheckState::HandedOver
                && (int) $journey->final_check_number === (int) $locked->check_number, 409, 'Hanya semakan muktamad yang telah diserahkan boleh disemak.');
            $last = (int) (ProfileFieldReview::query()->where('review_version_id', $locked->id)->where('field', $field)->max('revision') ?? 0);
            abort_unless($last === $expectedRevision, 409, 'Profil telah dikemas kini; muat semula.');
            $profile = $locked->profile?->profile_json ?? [];
            $original = $profile[$field] ?? null;
            if ($disposition === 'ACCEPTED') {
                abort_unless(is_string($original) && $original !== '', 422, 'Tiada nilai diekstrak untuk disahkan.');
                abort_unless(collect($profile['field_evidence'] ?? [])->contains('field', $field), 422, 'Bukti sumber diperlukan.');
                $value = $original;
            }
            if ($disposition === 'CORRECTED') {
                abort_unless(is_string($value) && trim($value) !== '', 422, 'Nilai pembetulan diperlukan.');
            }
            $review = new ProfileFieldReview;
            $review->forceFill(['review_version_id' => $locked->id, 'actor_id' => $officer->id, 'field' => $field, 'disposition' => $disposition,
                'value' => $disposition === 'REJECTED' ? null : trim((string) $value), 'reason' => $reason, 'revision' => $last + 1])->save();
            AuditLog::query()->create(['auditable_type' => ReviewVersion::class, 'auditable_id' => $locked->id, 'event' => 'airs_profile_field_review',
                'actor_id' => $officer->id, 'before_values' => ['field' => $field, 'extracted' => is_scalar($original) ? $original : null],
                'after_values' => ['disposition' => $disposition, 'value' => $review->value, 'revision' => $review->revision], 'ip_address' => request()?->ip()]);

            return $review;
        });
    }
}
