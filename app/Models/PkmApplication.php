<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'application_id',
    'permission_category',
    'permission_type',
    'existing_land_use',
    'proposed_land_use',
    'density',
    'plot_ratio',
    'number_of_units',
    'number_of_storeys',
    'building_height',
    'planning_standards',
    'technical_conditions',
    'approval_valid_until',
])]
class PkmApplication extends Model
{
    protected $table = 'app_pkm_applications';

    protected $primaryKey = 'application_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(PkmComponent::class, 'application_id', 'application_id');
    }

    protected function casts(): array
    {
        return [
            'density' => 'decimal:2',
            'plot_ratio' => 'decimal:3',
            'building_height' => 'decimal:2',
            'approval_valid_until' => 'date',
        ];
    }
}
