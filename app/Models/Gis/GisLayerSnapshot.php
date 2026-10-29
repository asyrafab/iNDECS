<?php

namespace App\Models\Gis;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GisLayerSnapshot extends Model
{
    protected $table = 'core.gis_layer_snapshots';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'feature_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(GisLayer::class, 'layer_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(GisFeature::class, 'snapshot_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
