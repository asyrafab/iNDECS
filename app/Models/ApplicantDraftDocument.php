<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file a PSP uploaded for one checklist item of a preparation draft (ADR-363). Not an official document. */
class ApplicantDraftDocument extends Model
{
    use HasUlids;

    protected $table = 'core.applicant_draft_documents';

    protected $guarded = ['*'];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ApplicantDraft::class, 'draft_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer', 'size_bytes' => 'integer', 'readable' => 'boolean', 'text_chars' => 'integer',
            'matched_keywords' => 'array', 'sort_score' => 'integer', 'scan_pending' => 'boolean', 'ocr_used' => 'boolean', 'ocr_unavailable' => 'boolean', 'confirmed_at' => 'datetime',
        ];
    }
}
