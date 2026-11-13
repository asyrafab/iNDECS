<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PromoteStaffAdministrator extends Command
{
    protected $signature = 'staff:superadmin {staff_number}';

    protected $description = 'Grant an existing active staff account unscoped system administrator access, with an audit record';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $user = User::query()->where('staff_number', (string) $this->argument('staff_number'))->lockForUpdate()->first();
            if (! $user || ! $user->is_active || $user->account_type !== 'staff') {
                $this->error('Akaun kakitangan aktif yang dipautkan tidak ditemui.');

                return self::FAILURE;
            }
            if (! DB::table('core.roles')->where('id', 'system_admin')->where('is_active', true)->where('account_type', 'staff')->exists()) {
                $this->error('Peranan pentadbir sistem belum tersedia.');

                return self::FAILURE;
            }
            if ($user->role === 'system_admin' && $user->district === null) {
                $this->info('Akaun ini sudah mempunyai akses superadmin.');

                return self::SUCCESS;
            }
            $before = $user->only(['role', 'district']);
            $user->forceFill(['role' => 'system_admin', 'district' => null])->save();
            AuditLog::query()->create([
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'event' => 'staff_superadmin_granted_by_console',
                'before_values' => $before,
                'after_values' => ['role' => 'system_admin', 'district' => null, 'source' => 'staff:superadmin'],
            ]);
            $this->info('Akses superadmin diberikan kepada kakitangan '.$user->staff_number.'.');

            return self::SUCCESS;
        });
    }
}
