<?php

namespace App\Enums;

enum CompletenessReviewStatus: string
{
    case InProgress = 'in_progress';
    case Returned = 'returned';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Dalam semakan',
            self::Returned => 'Dikembalikan untuk pindaan',
            self::Complete => 'Lengkap',
        };
    }
}
