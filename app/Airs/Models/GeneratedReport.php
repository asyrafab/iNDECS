<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class GeneratedReport extends Model
{
    use HasUlids;

    protected $table = 'airs.generated_reports';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['report_json' => 'array'];
    }
}
