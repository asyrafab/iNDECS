<?php

declare(strict_types=1);

namespace App\Airs\Models;

use App\Airs\Enums\EvaluationMethod;
use App\Airs\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReviewFinding extends Model
{
    use HasUlids;

    protected $table = 'airs.review_findings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applicable' => 'boolean',
            'status' => FindingStatus::class,
            'extracted_values' => 'array',
            'warnings' => 'array',
            'evaluation_method' => EvaluationMethod::class,
            'is_superseded' => 'boolean',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReviewRule::class, 'review_rule_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReviewEvidence::class);
    }

    public function reviewVersion(): BelongsTo
    {
        return $this->belongsTo(ReviewVersion::class);
    }
}
