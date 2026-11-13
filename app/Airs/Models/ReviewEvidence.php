<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReviewEvidence extends Model
{
    use HasUlids;

    protected $table = 'airs.review_evidence';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['bbox_normalised' => 'array'];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(ReviewFinding::class, 'review_finding_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ReviewFile::class, 'review_file_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ReviewPage::class, 'review_page_id');
    }
}
