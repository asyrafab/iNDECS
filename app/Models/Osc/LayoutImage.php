<?php

namespace App\Models\Osc;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayoutImage extends Model
{
    use HasUlids;

    protected $table = 'core.osc_layout_images';

    protected $guarded = [];

    // corners is written and read with PostGIS SQL, not Eloquent.
    protected $hidden = ['corners'];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'source_srid' => 'integer',
            'row_version' => 'integer',
        ];
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }
}
