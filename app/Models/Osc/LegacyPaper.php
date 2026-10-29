<?php

namespace App\Models\Osc;

use Illuminate\Database\Eloquent\Model;

class LegacyPaper extends Model
{
    protected $table = 'core.osc_legacy_papers';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    // geom is written and read with PostGIS SQL, not Eloquent.
    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }
}
