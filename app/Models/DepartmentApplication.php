<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Department assessment extension; writes belong to the authorised departmental service. */
abstract class DepartmentApplication extends Model
{
    protected $primaryKey = 'application_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
