<?php

namespace App\Enums;

enum DocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pengesahan',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
        };
    }
}
