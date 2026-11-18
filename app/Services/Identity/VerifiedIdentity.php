<?php

namespace App\Services\Identity;

final readonly class VerifiedIdentity
{
    public function __construct(public string $issuer, public string $subject, public string $name, public int $authenticatedAt) {}

    public function toArray(): array
    {
        return ['issuer' => $this->issuer, 'subject' => $this->subject, 'name' => $this->name, 'authenticated_at' => $this->authenticatedAt];
    }
}
