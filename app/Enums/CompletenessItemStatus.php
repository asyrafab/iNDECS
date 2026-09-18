<?php

namespace App\Enums;

enum CompletenessItemStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
    case Missing = 'missing';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum disemak',
            self::Complete => 'Lengkap',
            self::Missing => 'Tidak lengkap',
            self::NotApplicable => 'Tidak berkenaan',
        };
    }
}
