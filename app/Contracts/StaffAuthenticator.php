<?php

declare(strict_types=1);

namespace App\Contracts;

interface StaffAuthenticator
{
    /** Returns a verified profile, or null for rejected credentials. */
    public function authenticate(string $staffNumber, #[\SensitiveParameter] string $password): ?array;
}
