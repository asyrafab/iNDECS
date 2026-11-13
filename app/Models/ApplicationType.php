<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'name', 'primary_department_id', 'is_active'])]
class ApplicationType extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** Application types a PSP can prepare on the public portal (ADR-358/364). */
    public const PORTAL_TYPES = ['PKM', 'PB', 'PBK', 'PBS', 'PMT', 'TEL'];

    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'primary_department_id');
    }

    /** Active portal types whose department is active, in display order. */
    public function scopePortal(Builder $query): Builder
    {
        return $query->whereIn('id', self::PORTAL_TYPES)->where('is_active', true)
            ->whereHas('primaryDepartment', fn (Builder $department) => $department->where('is_active', true))
            ->with('primaryDepartment')->orderBy('primary_department_id')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
