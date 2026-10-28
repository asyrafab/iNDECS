<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_type_id', 'document_name', 'allowed_extensions', 'max_file_size_kb', 'is_required', 'sort_order', 'status', 'document_id', 'remarks', 'checked_by', 'checked_at'])]
final class DocumentChecklistItem extends Model
{
    use HasUlids;

    protected $table = 'core.document_checklist_items';

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(DocumentChecklist::class, 'checklist_id');
    }

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }
}
