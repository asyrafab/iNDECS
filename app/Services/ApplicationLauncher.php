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
            $applications[] = ['name' => 'iNDECS Perancang', 'department' => 'perancang', 'icon' => 'map', 'description' => 'Permohonan Kebenaran Merancang', 'url' => route('perancang.index')];
        }
        if ($user->canAccessBangunan() || $user->canAccessBangunanLegacy()) {
            $applications[] = ['name' => 'iNDECS Bangunan', 'department' => 'bangunan', 'icon' => 'building-2', 'description' => 'Pelan, permit dan rekod bangunan', 'url' => $user->canAccessBangunan() ? route('bangunan.index') : route('bangunan.legacy.index')];
        }
        // Planned systems are visible to the administrator without advertising working navigation.
        $administrator = $user->role === 'system_admin' && DB::table('core.roles')->where('id', 'system_admin')->where('account_type', 'staff')->where('is_active', true)->exists();
        if ($administrator || $user->canAccessKejuruteraan()) {
            $applications[] = ['name' => 'iNDECS Kejuruteraan', 'department' => 'kejuruteraan', 'icon' => 'ruler', 'description' => 'Pelan Lampu Jalan (LJUP) dan semakan AIRS',
                'url' => $user->canAccessKejuruteraan() ? route('kejuruteraan.index') : null];
        }
        if ($administrator) {
            $applications[] = ['name' => 'iNDECS Landskap', 'department' => 'landskap', 'icon' => 'layers', 'description' => 'Pelan landskap', 'url' => null];
        }
        if ($user->canAccessOsc()) {
            $applications[] = ['name' => 'eOSC+', 'brand' => 'eosc', 'icon' => 'folder-open', 'description' => 'Agenda dan mesyuarat OSC', 'url' => route('osc.meetings.index')];
        }
        if ($administrator) {
            $applications[] = ['name' => 'AIRS', 'brand' => 'airs', 'icon' => 'search', 'description' => 'AI Review Studio · Semakan pelan', 'url' => $user->canAccessKejuruteraan() ? route('kejuruteraan.index') : null];
            $applications[] = ['name' => 'E-Plan Submission', 'brand' => 'esri', 'icon' => 'square-dashed', 'description' => 'Penghantaran data spatial', 'url' => 'https://spgis.mbsp.gov.my/ePlanSubmission'];
        }

        return $applications;
    }
}
