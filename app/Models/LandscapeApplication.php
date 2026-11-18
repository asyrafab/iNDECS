<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'landscape_category',
    'open_space_area',
    'softscape_area',
    'hardscape_area',
    'existing_trees',
    'retained_trees',
    'proposed_trees',
    'proposed_species',
    'buffer_planting',
    'recreation_facilities',
    'replacement_conditions',
])]
final class LandscapeApplication extends DepartmentApplication
{
    public const TYPES = ['PL'];

    public const DISTRICTS = ['Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan'];

    protected $table = 'landskap.app_pl_applications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'open_space_area' => 'decimal:4',
            'softscape_area' => 'decimal:4',
            'hardscape_area' => 'decimal:4',
            'existing_trees' => 'integer',
            'retained_trees' => 'integer',
            'proposed_trees' => 'integer',
        ];
    }
}
