<?php

declare(strict_types=1);

namespace App\Airs\Models;

use App\Airs\Enums\ReviewCaseStatus;
use App\Models\Application;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One AIRS review case per iNDECS application; each check is one version (check_number 1 or 2). */
final class ReviewCase extends Model
{
    use HasUlids;

    protected $table = 'airs.review_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReviewCaseStatus::class,
            'selected_lighting_types' => 'array',
            'warnings' => 'array',
            'payment_cleared_at' => 'immutable_datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReviewFile::class);
    }

    /** Files of the current check (submission_number = check number). */
    public function activeFiles(): HasMany
    {
        return $this->files()->where('submission_number', $this->document_set_version ?: 1);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReviewVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ReviewVersion::class)->latestOfMany('check_number');
    }
}
