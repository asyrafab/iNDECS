<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AcceptedSubmission extends Model
{
    use HasUlids;

    protected $table = 'core.accepted_submissions';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['document_manifest' => 'array', 'missing_required_documents' => 'array', 'payment_cleared_at' => 'datetime', 'accepted_at' => 'datetime'];
    }
}
