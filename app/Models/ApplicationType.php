<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'name', 'primary_department_id', 'is_active'])]
class ApplicationType extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'primary_department_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
