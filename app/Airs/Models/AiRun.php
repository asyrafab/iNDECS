<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AiRun extends Model
{
    use HasUlids;

    protected $table = 'airs.ai_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_manifest' => 'array',
            'parsed_response' => 'array',
            'usage_json' => 'array',
            'coverage_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
