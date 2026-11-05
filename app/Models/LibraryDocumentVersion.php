<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable file version of a library document (append-only table). */
class LibraryDocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'core.library_document_versions';

    protected $guarded = ['*'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LibraryDocument::class, 'document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'size_bytes' => 'integer', 'created_at' => 'datetime'];
    }
}
