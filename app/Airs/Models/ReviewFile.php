<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReviewFile extends Model
{
    use HasUlids;

    protected $table = 'airs.review_files';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean', 'is_duplicate' => 'boolean'];
    }

    public function reviewCase(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ReviewPage::class);
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(DocumentClassification::class);
    }
}
