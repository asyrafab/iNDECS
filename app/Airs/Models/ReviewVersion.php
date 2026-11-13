<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ReviewVersion extends Model
{
    use HasUlids;

    protected $table = 'airs.review_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'check_number' => 'integer', 'score' => 'integer'];
    }

    public function reviewCase(): BelongsTo
    {
        return $this->belongsTo(ReviewCase::class);
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(DocumentClassification::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ApplicationProfile::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }
}
