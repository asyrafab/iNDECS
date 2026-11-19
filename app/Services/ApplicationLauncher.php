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
        $administrator = $user->role === 'system_admin' && DB::table('core.roles')->where('id', 'system_admin')->where('account_type', 'staff')->where('is_active', true)->exists();

        return [
            ['name' => 'iNDECS Planning', 'department' => 'perancang', 'icon' => 'map', 'description' => 'Jabatan Perancang Bandar',
                'url' => $user->canAccessPkm() ? route('perancang.dashboard') : null],
            ['name' => 'iNDECS Building', 'department' => 'bangunan', 'icon' => 'building-2', 'description' => 'Jabatan Bangunan',
                'url' => $user->canAccessBangunan() ? route('bangunan.dashboard') : ($user->canAccessBangunanLegacy() ? route('bangunan.legacy.index') : null)],
            ['name' => 'iNDECS Engineering', 'department' => 'kejuruteraan', 'icon' => 'ruler', 'description' => 'Jabatan Kejuruteraan',
                'url' => $user->canAccessKejuruteraan() ? route('kejuruteraan.dashboard') : null],
            ['name' => 'iNDECS Landscape', 'department' => 'landskap', 'icon' => 'layers', 'description' => 'Jabatan Landskap',
                'url' => $user->canAccessLandskap() ? route('landskap.dashboard') : null],
            ['name' => 'NADI', 'brand' => 'eosc', 'icon' => 'folder-open', 'description' => 'Navigation, Analysis, Decisions & Intelligence',
                'url' => null, 'unavailable' => 'Aplikasi mesyuarat belum tersedia'],
            ['name' => 'E-Plan Submission', 'brand' => 'esri', 'icon' => 'square-dashed', 'description' => 'Penghantaran data spatial',
                'url' => $administrator ? 'https://spgis.mbsp.gov.my/ePlanSubmission' : null],
            ['name' => 'Daftar Mesyuarat', 'module' => 'meeting-registration', 'icon' => 'files', 'description' => 'Pendaftaran kertas mesyuarat OSC',
                'url' => $user->canAccessOsc() ? route('osc.meetings.index') : null],
        ];
    }
}
