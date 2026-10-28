<?php

namespace App\Models;

use App\Enums\DocumentVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scan_status',
    'scanned_at',
    'application_id',
    'document_type_id',
    'revision_number',
    'original_name',
    'storage_path',
    'mime_type',
    'size_bytes',
    'checksum_sha256',
    'uploaded_by',
    'uploaded_at',
    'verification_status',
    'verification_remarks',
    'verified_by',
    'verified_at',
    'is_current',
    'supersedes_id',
])]
class ApplicationDocument extends Model
{
    use HasUlids;

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'scanned_at' => 'datetime',
            'verified_at' => 'datetime',
            'verification_status' => DocumentVerificationStatus::class,
            'is_current' => 'boolean',
        ];
    }
}
