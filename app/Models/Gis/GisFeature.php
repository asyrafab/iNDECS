<?php

namespace App\Models\Gis;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GisFeature extends Model
{
    protected $table = 'core.gis_features';

    public $timestamps = false;

    protected $guarded = [];

    // Geometry columns (geom, geom_web) are written and read with PostGIS SQL, not Eloquent.
    protected $hidden = ['geom', 'geom_web'];

    protected function casts(): array
    {
        return ['attributes' => 'array'];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(GisLayerSnapshot::class, 'snapshot_id');
    }
}
