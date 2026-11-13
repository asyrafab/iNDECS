<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentClassification extends Model
{
    use HasUlids;

    protected $table = 'airs.document_classifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['secondary_tags' => 'array', 'evidence_page_numbers' => 'array', 'warnings' => 'array'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ReviewFile::class, 'review_file_id');
    }
}
