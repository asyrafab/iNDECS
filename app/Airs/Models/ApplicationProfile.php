<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class ApplicationProfile extends Model
{
    use HasUlids;

    protected $table = 'airs.application_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['profile_json' => 'array', 'confidence_summary' => 'array', 'conflicts_json' => 'array'];
    }
}
