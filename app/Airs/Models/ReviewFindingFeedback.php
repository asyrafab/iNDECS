<?php

declare(strict_types=1);

namespace App\Airs\Models;

use App\Airs\Enums\FindingStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An officer's attributed, append-only comment on one AIRS finding. */
final class ReviewFindingFeedback extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'airs.review_finding_feedbacks';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => FindingStatus::class, 'created_at' => 'immutable_datetime'];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(ReviewFinding::class, 'review_finding_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
