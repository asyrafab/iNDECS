<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'development_address',
    'district',
    'mukim',
    'state',
    'site_area',
    'site_area_unit',
    'arcgis_global_id',
])]
class Site extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['site_area' => 'decimal:4'];
    }
}
