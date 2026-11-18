<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ApplicationRecommendation extends Model
{
    use HasUlids;

    protected $table = 'core.application_recommendations';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'revision' => 'integer', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function letterDraft(): HasOne
    {
        return $this->hasOne(ApplicationLetterDraft::class, 'recommendation_id');
    }
}
