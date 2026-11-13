<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A fee payment for the paid AIRS check of one applicant draft. */
class CheckPayment extends Model
{
    use HasUlids;

    protected $table = 'core.check_payments';

    protected $guarded = ['*'];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ApplicantDraft::class, 'draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['amount_sen' => 'integer', 'paid_at' => 'immutable_datetime'];
    }
}
