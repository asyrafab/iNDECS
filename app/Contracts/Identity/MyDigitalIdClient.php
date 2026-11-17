<?php

namespace App\Contracts\Identity;

use App\Services\Identity\VerifiedIdentity;

interface MyDigitalIdClient
{
    public function authorizationUrl(array $transaction): string;

    public function complete(string $code, array $transaction): VerifiedIdentity;
}
