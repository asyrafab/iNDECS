<?php

namespace App\Models\Gis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GisLayer extends Model
{
    protected $table = 'core.gis_layers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'match_attributes' => 'array',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(GisLayerSnapshot::class, 'layer_id');
    }

    public function activeSnapshot(): BelongsTo
    {
        return $this->belongsTo(GisLayerSnapshot::class, 'active_snapshot_id');
    }
}
