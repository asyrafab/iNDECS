<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A public holiday excluded from working-day deadlines. */
class PublicHoliday extends Model
{
    protected $table = 'core.public_holidays';

    protected $primaryKey = 'date';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['date', 'name'];
}
