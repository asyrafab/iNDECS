<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class LinkStaffAccount extends Command
{
    protected $signature = 'staff:link {email} {staff_number}';

    protected $description = 'Link an existing approved iNDECS account to its verified MBSP staff number';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (str_contains($email, '\@')) {
            $this->error('E-mel tidak sah. Dalam PowerShell, gunakan @ tanpa backslash.');

            return self::FAILURE;
        }

        $staffNumber = (string) $this->argument('staff_number');
        if (! preg_match('/^[0-9]{1,30}$/', $staffNumber)) {
            $this->error('Nombor kakitangan tidak sah.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($staffNumber): int {
            $email = (string) $this->argument('email');
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if (! $user) {
                $this->error('Akaun iNDECS untuk e-mel ini belum wujud. Jalankan staff:approve dahulu.');

                return self::FAILURE;
            }

            if (! $user->canAccessStaffWorkspace()) {
                $this->error('Akaun iNDECS ini belum aktif atau belum mempunyai akses ruang kerja staff.');

                return self::FAILURE;
            }

            if (filled($user->staff_number) && $user->staff_number !== $staffNumber) {
                $this->error('Akaun ini telah dipautkan kepada nombor kakitangan lain.');

                return self::FAILURE;
            }

            if (User::query()->where('staff_number', $staffNumber)->where('id', '!=', $user->id)->exists()) {
                $this->error('Nombor kakitangan ini telah dipautkan kepada akaun lain.');

                return self::FAILURE;
            }

            if ($user->staff_number === $staffNumber) {
                $this->info('Pautan kakitangan telah tersedia.');

                return self::SUCCESS;
            }

            $user->forceFill(['staff_number' => $staffNumber])->save();
            AuditLog::query()->create([
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'event' => 'staff_account_linked_by_console',
                'before_values' => ['staff_number' => null],
                'after_values' => ['staff_number' => $staffNumber, 'source' => 'staff:link'],
            ]);
            $this->info('Pautan kakitangan disimpan.');

            return self::SUCCESS;
        });
    }
}
