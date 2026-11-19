<?php

declare(strict_types=1);

namespace App\Services\Kejuruteraan;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EngineeringApplicationAccess
{
    public const TYPES = ['PKT', 'PKP'];

    public const DISTRICTS = ['Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan'];

    public function types(User $user, string $action = 'access'): array
    {
        return array_values(array_filter(self::TYPES, fn ($type) => $user->canPerformEngineering('access', $type) && $user->canPerformEngineering($action, $type)));
    }

    public function applications(User $user): Builder
    {
        return Application::query()->where('responsible_department_id', 'JKEJ')->where(function (Builder $query) use ($user): void {
            $query->where(fn (Builder $native) => $native->whereIn('application_type_id', $this->types($user))->visibleTo($user));
            if ($user->canPerformLjup('access')) {
                $query->orWhere(fn (Builder $ljup) => $ljup->visibleToLjupOfficer($user));
            }
        });
    }
}
