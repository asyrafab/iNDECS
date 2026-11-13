<?php

declare(strict_types=1);

namespace App\Airs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An officer's attributed, append-only disposition on one MATRIX-extracted profile field. */
final class ProfileFieldReview extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'airs.profile_field_reviews';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
