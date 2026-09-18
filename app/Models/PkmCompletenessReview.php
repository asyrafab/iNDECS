<?php

namespace App\Models;

use App\Enums\CompletenessReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'application_id',
    'cycle_number',
    'status',
    'opened_by',
    'opened_at',
    'decided_by',
    'decided_at',
    'decision_remarks',
])]
class PkmCompletenessReview extends Model
{
    use HasUlids;

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PkmCompletenessItem::class, 'review_id')->orderBy('id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'cycle_number' => 'integer',
            'status' => CompletenessReviewStatus::class,
            'opened_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
