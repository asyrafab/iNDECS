<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CorePermissionService
{
    public function userCan(User $user, string $permission, ?string $departmentId = null, ?string $applicationTypeId = null): bool
    {
        if (! $user->is_active || $user->account_type !== 'staff' || blank($user->role)) {
            return false;
        }

        return DB::table('core.role_permissions as rp')
            ->join('core.roles as r', 'r.id', '=', 'rp.role_id')
            ->join('core.permissions as p', 'p.name', '=', 'rp.permission_name')
            ->where('r.id', $user->role)
            ->where('r.account_type', 'staff')
            ->where('r.is_active', true)
            ->where('p.name', $permission)
            ->where(function ($query) use ($departmentId): void {
                $query->whereNull('rp.department_id');

                if ($departmentId !== null) {
                    $query->orWhere('rp.department_id', $departmentId);
                }
            })
            ->where(function ($query) use ($applicationTypeId): void {
                $query->whereNull('rp.application_type_id');

                if ($applicationTypeId !== null) {
                    $query->orWhere('rp.application_type_id', $applicationTypeId);
                }
            })
            ->exists();
    }
}
