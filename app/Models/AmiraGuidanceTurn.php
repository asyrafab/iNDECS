<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AmiraGuidanceTurn extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'core.amira_guidance_turns';

    protected $fillable = ['user_id', 'context', 'request_id', 'question', 'heading', 'reply'];
}
