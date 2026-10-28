<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['building_category', 'building_use', 'number_of_storeys', 'number_of_units', 'gross_floor_area', 'technical_notes'])]
final class BangunanApplication extends Model
{
    public const TYPES = ['PB', 'PBK', 'PBS', 'PMT', 'TEL'];

    public const DISTRICTS = ['Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan'];

    protected $table = 'bangunan.app_pb_applications';

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return ['number_of_storeys' => 'integer', 'number_of_units' => 'integer', 'gross_floor_area' => 'decimal:2'];
    }
}
