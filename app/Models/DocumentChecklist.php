<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['application_id', 'catalogue_version', 'source_reference', 'status', 'opened_by', 'opened_at'])]
final class DocumentChecklist extends Model
{
    use HasUlids;

    protected $table = 'core.document_checklists';

    public function items(): HasMany
    {
        return $this->hasMany(DocumentChecklistItem::class, 'checklist_id')->orderBy('sort_order')->orderBy('id');
    }
}
