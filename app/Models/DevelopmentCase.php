<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'title', 'description', 'overall_status'])]
class DevelopmentCase extends Model
{
    use HasUlids;

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
