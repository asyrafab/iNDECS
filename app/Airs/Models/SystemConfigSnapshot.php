<?php

declare(strict_types=1);

namespace App\Airs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class SystemConfigSnapshot extends Model
{
    use HasUlids;

    protected $table = 'airs.system_config_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['config_json' => 'array'];
    }
}
