<?php

declare(strict_types=1);

namespace App\Airs\Models;

use App\Airs\Enums\EvaluationMethod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class ReviewRule extends Model
{
    use HasUlids;

    protected $table = 'airs.review_rules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'applies_when' => 'array',
            'applicability_tags' => 'array',
            'required_evidence_types' => 'array',
            'evidence_required' => 'boolean',
            'evaluation_method' => EvaluationMethod::class,
            'manual_confirmation_required' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
