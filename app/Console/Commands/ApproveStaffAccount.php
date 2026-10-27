<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ApproveStaffAccount extends Command
{
    protected $signature = 'staff:approve {email} {staff_number} {--name=} {--department=JPBD} {--role=planning_officer}';

    protected $description = 'Create or approve a local iNDECS staff shell account and link it to a verified MBSP staff number';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $staffNumber = (string) $this->argument('staff_number');
        $departmentId = strtoupper(trim((string) $this->option('department')));
        $role = trim((string) $this->option('role'));
        $name = trim((string) ($this->option('name') ?: Str::headline(Str::before($email, '@'))));

        if (str_contains($email, '\@') || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('E-mel tidak sah. Dalam PowerShell, gunakan @ tanpa backslash.');

            return self::FAILURE;
        }

        if (! preg_match('/^[0-9]{1,30}$/', $staffNumber)) {
            $this->error('Nombor kakitangan tidak sah.');

            return self::FAILURE;
        }

        if (! Department::query()->whereKey($departmentId)->where('is_active', true)->exists()) {
            $this->error('Jabatan tidak sah atau tidak aktif.');

            return self::FAILURE;
        }

        $hasPkmAccess = DB::table('core.role_permissions')
            ->where('role_id', $role)
            ->where('permission_name', 'perancang.pkm.access')
            ->where(function ($query) use ($departmentId): void {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->where(function ($query): void {
                $query->whereNull('application_type_id')->orWhere('application_type_id', 'PKM');
            })
            ->exists();

        if (! $hasPkmAccess) {
            $this->error('Role dan jabatan ini belum mempunyai akses PKM.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($departmentId, $email, $name, $role, $staffNumber): int {
            if (User::query()->where('staff_number', $staffNumber)->where('email', '!=', $email)->exists()) {
                $this->error('Nombor kakitangan ini telah dipautkan kepada akaun lain.');

                return self::FAILURE;
            }

            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if ($user && $user->account_type !== 'staff') {
                $this->error('E-mel ini telah digunakan oleh akaun awam. Gunakan e-mel staff lain.');

                return self::FAILURE;
            }

            $before = $user?->only(['staff_number', 'department_id', 'role', 'is_active', 'account_type']);
            $user ??= new User(['email' => $email]);
            $user->forceFill([
                'name' => $user->name ?: $name,
                'email' => $email,
                'email_verified_at' => $user->email_verified_at ?: now(),
                'password' => $user->password ?: Hash::make(Str::password(40)),
                'department_id' => $departmentId,
                'role' => $role,
                'is_active' => true,
                'account_type' => 'staff',
                'has_password' => false,
                'staff_number' => $staffNumber,
            ])->save();

            AuditLog::query()->create([
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'event' => 'staff_account_approved_by_console',
                'before_values' => $before,
                'after_values' => [
                    'staff_number' => $staffNumber,
                    'department_id' => $departmentId,
                    'role' => $role,
                    'source' => 'staff:approve',
                ],
            ]);

            $this->info('Akaun staff tempatan diluluskan dan dipautkan.');

            return self::SUCCESS;
        });
    }
}
