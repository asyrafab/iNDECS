<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ApplicationLauncher
{
    public function forUser(User $user): array
    {
        if ($user->isPublicAccount()) {
            return [['name' => 'Portal Awam', 'icon' => 'files', 'description' => 'Akaun dan akses pengguna awam', 'url' => route('portal.home')]];
        }
        if (! $user->is_active || $user->account_type !== 'staff') {
            return [];
        }

        $applications = [];
        if ($user->canAccessPkm()) {
            $applications[] = ['name' => 'iNDECS Planning', 'department' => 'perancang', 'icon' => 'map', 'description' => 'Jabatan Perancang Bandar', 'url' => route('perancang.index')];
        }
        if ($user->canAccessBangunan() || $user->canAccessBangunanLegacy()) {
            $applications[] = ['name' => 'iNDECS Building', 'department' => 'bangunan', 'icon' => 'building-2', 'description' => 'Jabatan Bangunan', 'url' => $user->canAccessBangunan() ? route('bangunan.index') : route('bangunan.legacy.index')];
        }
        // Planned systems are visible to the administrator without advertising working navigation.
        $administrator = $user->role === 'system_admin' && DB::table('core.roles')->where('id', 'system_admin')->where('account_type', 'staff')->where('is_active', true)->exists();
        if ($administrator || $user->canAccessKejuruteraan()) {
            $applications[] = ['name' => 'iNDECS Engineering', 'department' => 'kejuruteraan', 'icon' => 'ruler', 'description' => 'Jabatan Kejuruteraan',
                'url' => $user->canAccessKejuruteraan() ? route('kejuruteraan.index') : null];
        }
        if ($administrator || $user->canAccessLandskap()) {
            $applications[] = ['name' => 'iNDECS Landscape', 'department' => 'landskap', 'icon' => 'layers', 'description' => 'Jabatan Landskap',
                'url' => $user->canAccessLandskap() ? route('landskap.index') : null];
        }
        if ($user->canAccessOsc()) {
            $applications[] = ['name' => 'NADI', 'brand' => 'eosc', 'icon' => 'folder-open', 'description' => 'Navigation, Analysis, Decisions & Intelligence · eOSC', 'url' => route('osc.meetings.index')];
        }
        if ($administrator) {
            $applications[] = ['name' => 'E-Plan Submission', 'brand' => 'esri', 'icon' => 'square-dashed', 'description' => 'Penghantaran data spatial', 'url' => 'https://spgis.mbsp.gov.my/ePlanSubmission'];
        }

        return $applications;
    }
}
