<?php

declare(strict_types=1);

namespace App\Models;

final class LjupApplication extends DepartmentApplication
{
    protected $table = 'kejuruteraan.app_ljup_applications';

    protected function casts(): array
    {
        return ['lighting_types' => 'array'];
    }
}
