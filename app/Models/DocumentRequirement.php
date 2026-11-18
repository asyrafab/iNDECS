<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_type_id',
    'permission_category',
    'permission_type',
    'document_type_id',
    'is_required',
    'sort_order',
    'is_active',
    'conditions',
    'guidance',
    'precheck_keywords',
    'max_files',
])]
class DocumentRequirement extends Model
{
    protected $table = 'core.document_requirements';

    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'conditions' => 'array',
            'precheck_keywords' => 'array',
            'max_files' => 'integer',
        ];
    }
}
