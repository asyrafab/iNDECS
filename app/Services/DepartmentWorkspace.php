<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class DepartmentWorkspace
{
    public function resolve(Request $request, User $user): ?string
    {
        $allowed = array_filter([
            'perancang' => $user->canAccessPkm(),
            'bangunan' => $user->canAccessBangunan() || $user->canAccessBangunanLegacy(),
            'kejuruteraan' => $user->canAccessKejuruteraan(),
            'landskap' => $user->canAccessLandskap(),
        ]);
        $key = 'workspace.department.'.$user->id;
        foreach (array_keys($allowed) as $department) {
            if ($request->routeIs($department.'.*') || ($department === 'perancang' && $request->routeIs('dashboard'))) {
                if ($request->hasSession()) {
                    $request->session()->put($key, $department);
                }

                return $department;
            }
        }
        $remembered = $request->hasSession() ? $request->session()->get($key) : null;
        if (is_string($remembered) && isset($allowed[$remembered])) {
            return $remembered;
        }
        if ($request->hasSession()) {
            $request->session()->forget($key);
        }
        $own = match ($user->department_id) {
            'JPBD' => 'perancang', 'JBANG' => 'bangunan', 'JKEJ' => 'kejuruteraan', 'JLAND' => 'landskap', default => null,
        };

        return isset($allowed[$own]) ? $own : array_key_first($allowed);
    }
}
