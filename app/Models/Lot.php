<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'lot_number',
    'lot_type',
    'section',
    'mukim',
    'district',
    'state',
    'land_area',
    'land_area_unit',
])]
class Lot extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['land_area' => 'decimal:4'];
    }
}
