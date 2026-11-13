<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class PageRegion extends Model
{
    use HasUlids;

    protected $table = 'airs.page_regions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['bbox_normalised' => 'array'];
    }
}
