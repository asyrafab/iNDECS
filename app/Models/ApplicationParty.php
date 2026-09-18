<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'party_id', 'role', 'is_primary', 'submitted_snapshot'])]
class ApplicationParty extends Model
{
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'submitted_snapshot' => 'array',
        ];
    }
}
