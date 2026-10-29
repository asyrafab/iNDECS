<?php

namespace App\Models\Osc;

use Illuminate\Database\Eloquent\Model;

class DepartmentSetting extends Model
{
    public $incrementing = false;

    protected $table = 'core.osc_department_settings';

    protected $primaryKey = 'department_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['requires_endorsement' => 'boolean'];
    }
}
