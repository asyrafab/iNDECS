<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReviewPage extends Model
{
    use HasUlids;

    protected $table = 'airs.review_pages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['processing_metadata' => 'array'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ReviewFile::class, 'review_file_id');
    }
}
