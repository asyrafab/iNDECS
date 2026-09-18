<?php

namespace App\Models;

use App\Enums\CompletenessItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'review_id',
    'document_requirement_id',
    'document_type_id',
    'requirement_name',
    'is_required',
    'status',
    'remarks',
    'checked_by',
    'checked_at',
])]
class PkmCompletenessItem extends Model
{
    public function review(): BelongsTo
    {
        return $this->belongsTo(PkmCompletenessReview::class, 'review_id');
    }

    public function documentRequirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'status' => CompletenessItemStatus::class,
            'checked_at' => 'datetime',
        ];
    }
}
