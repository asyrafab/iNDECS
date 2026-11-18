<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaidCheckState;
use App\Models\Concerns\HasExtensionAttributes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A draft's paid AIRS check journey: payment, check 1, optional amendment and check 2, handover. */
class PaidCheckJourney extends Model
{
    use HasExtensionAttributes;
    use HasUlids;

    protected $table = 'core.paid_check_journeys';

    protected $guarded = ['*'];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ApplicantDraft::class, 'draft_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CheckPayment::class, 'payment_id');
    }

    protected function extensionAttributeCasts(): array
    {
        return ['lighting_types' => 'array'];
    }

    protected function extensionAttributeStorage(): array
    {
        return ['table' => 'kejuruteraan.app_ljup_check_particulars', 'key' => 'journey_id', 'fields' => ['lighting_types']];
    }

    protected function casts(): array
    {
        return [
            'state' => PaidCheckState::class, 'lighting_types' => 'array', 'final_check_number' => 'integer',
            'amendment_due_at' => 'immutable_datetime', 'extended_at' => 'immutable_datetime', 'amended_at' => 'immutable_datetime',
            'handed_over_at' => 'immutable_datetime',
        ];
    }
}
