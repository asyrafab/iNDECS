<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BangunanApplication;
use App\Models\User;
use App\Services\Kejuruteraan\EngineeringApplicationAccess;
use App\Services\Tasks\OfficerTaskAccess;
use Illuminate\Http\Request;

final class DepartmentWorkspace
{
    public function menu(Request $request, User $user, ?string $department): array
    {
        $native = match ($department) {
            'perancang' => $user->canAccessPkm(), 'bangunan' => $user->canAccessBangunan(),
            'kejuruteraan' => $user->canAccessKejuruteraan(), 'landskap' => $user->canAccessLandskap(), default => false,
        };
        $legacy = $department === 'bangunan' && $user->canAccessBangunanLegacy();
        $register = $native && match ($department) {
            'perancang' => $user->canPerformPkm('perancang.pkm.register'),
            'bangunan' => collect(BangunanApplication::TYPES)->contains(fn ($type) => $user->canPerformBangunan('access', $type) && $user->canPerformBangunan('register', $type)),
            'landskap' => $user->canPerformLandskap('register'),
            'kejuruteraan' => app(EngineeringApplicationAccess::class)->types($user, 'register') !== [],
            default => false,
        };
        $item = fn (string $label, string $icon, ?string $url, array $patterns, string $reason = 'Tiada kebenaran akses') => [
            'label' => $label, 'icon' => $icon, 'url' => $url, 'active' => $url !== null && $request->routeIs(...$patterns), 'reason' => $reason,
        ];
        $records = $department ? [$department.'.index', $department.'.show'] : [];
        if (in_array($department, ['bangunan', 'landskap'], true)) {
            $records = [...$records, $department.'.documents.*', $department.'.document-catalogue.*'];
        }
        $work = [
            $item('Dashboard jabatan', 'layout-dashboard', $native ? route($department.'.dashboard') : null, [$department.'.dashboard', ...($department === 'perancang' ? ['dashboard'] : [])]),
            $item('Permohonan', 'files', $native ? route($department.'.index') : ($legacy ? route('bangunan.legacy.index') : null), $native ? $records : ['bangunan.legacy.*']),
            $item('Daftar permohonan', 'file-plus-2', $register ? route($department.'.create') : null, [$department.'.create'],
                'Tiada kebenaran akses'),
        ];
        if ($native && $legacy) {
            $work[] = $item('Carian Rekod Warisan', 'archive', route('bangunan.legacy.index'), ['bangunan.legacy.*']);
        }
        $work[] = $item('Tugasan pegawai', 'files', app(OfficerTaskAccess::class)->types($user) !== [] ? route('tasks.index') : null, ['tasks.*']);
        $work[] = $item('Daftar Mesyuarat', 'folder-open', $user->canAccessOsc() ? route('osc.meetings.index') : null, ['osc.*']);

        return ['work' => $work, 'help' => [
            $item('Perpustakaan jabatan', 'files', $user->canViewLibrary() ? route('library.index') : null, ['library.*']),
        ]];
    }

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
