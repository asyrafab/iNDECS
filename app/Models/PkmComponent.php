<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'name', 'category', 'number_of_units', 'floor_area', 'notes'])]
class PkmComponent extends Model
{
    use HasUlids;

    protected $table = 'app_pkm_components';

    public function pkmApplication(): BelongsTo
    {
        return $this->belongsTo(PkmApplication::class, 'application_id', 'application_id');
    }

    protected function casts(): array
    {
        return ['floor_area' => 'decimal:2'];
    }
}
