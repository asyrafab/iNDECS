<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AirsReview extends Model
{
    use HasUlids;

    protected $table = 'integration.airs_reviews';

    protected $guarded = [];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AcceptedSubmission::class, 'submission_id');
    }

    protected function casts(): array
    {
        return ['lighting_types' => 'array', 'result' => 'array'];
    }
}
