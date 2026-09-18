<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'description',
    'allowed_extensions',
    'max_file_size_kb',
    'is_active',
])]
class DocumentType extends Model
{
    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /** @return list<string> */
    public function allowedExtensions(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $extension): string => strtolower(trim($extension)),
            explode(',', $this->allowed_extensions),
        )));
    }

    protected function casts(): array
    {
        return [
            'max_file_size_kb' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
