<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\StaffAuthenticator;
use Illuminate\Console\Command;
use RuntimeException;

final class CheckStaffCredential extends Command
{
    protected $signature = 'staff:check-login {staff_number} {--prehashed-md5 : Prompt for a pre-hashed MD5 value exactly as used in Postman diagnostics}';

    protected $description = 'Check a staff number/password against the configured MBSP login service without creating or linking iNDECS users';

    public function handle(StaffAuthenticator $authenticator): int
    {
        $staffNumber = (string) $this->argument('staff_number');
        if (! preg_match('/^[0-9]{1,30}$/', $staffNumber)) {
            $this->error('Nombor kakitangan tidak sah.');

            return self::FAILURE;
        }

        $prehashed = $this->option('prehashed-md5') === true;
        $password = $this->secret($prehashed ? 'Nilai MD5 dari Postman' : 'Kata laluan MBSP');
        if (! is_string($password) || $password === '') {
            $this->error('Kata laluan diperlukan.');

            return self::FAILURE;
        }

        if ($prehashed && ! preg_match('/^[a-f0-9]{32}$/i', $password)) {
            $this->error('Nilai MD5 tidak sah.');

            return self::FAILURE;
        }

        if ($prehashed) {
            config(['services.mbsp_staff.password_mode' => 'raw']);
        }

        try {
            $profile = $authenticator->authenticate($staffNumber, $password);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($profile === null) {
            $this->warn('MBSP menolak nombor kakitangan atau kata laluan ini.');

            return self::FAILURE;
        }

        $this->info('MBSP menerima nombor kakitangan dan kata laluan ini.');

        return self::SUCCESS;
    }
}
