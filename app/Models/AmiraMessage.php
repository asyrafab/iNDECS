<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only turn in an AMIRA conversation (ADR-369). Bodies are never logged. */
class AmiraMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'core.amira_messages';

    protected $fillable = ['role', 'body', 'sources', 'proposal', 'fallback', 'mentions_outcome', 'model', 'latency_ms'];

    protected function casts(): array
    {
        return ['sources' => 'array', 'proposal' => 'array', 'fallback' => 'boolean', 'mentions_outcome' => 'boolean', 'created_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AmiraConversation::class, 'conversation_id');
    }
}
