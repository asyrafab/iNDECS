<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'name', 'is_active'])]
class Department extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function applicationTypes(): HasMany
    {
        return $this->hasMany(ApplicationType::class, 'primary_department_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
