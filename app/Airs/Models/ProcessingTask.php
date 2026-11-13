<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class ProcessingTask extends Model
{
    use HasUlids;

    protected $table = 'airs.processing_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
