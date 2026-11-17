<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class ApplicationLetterDraft extends Model
{
    use HasUlids;

    protected $table = 'core.application_letter_drafts';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['content_snapshot' => 'array', 'created_at' => 'datetime'];
    }
}
