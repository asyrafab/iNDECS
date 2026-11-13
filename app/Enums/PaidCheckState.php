<?php

declare(strict_types=1);

namespace App\Enums;

enum PaidCheckState: string
{
    case Check1Running = 'CHECK_1_RUNNING';
    case AwaitingApplicant = 'AWAITING_APPLICANT';
    case Check2Running = 'CHECK_2_RUNNING';
    case HandedOver = 'HANDED_OVER';

    public function label(): string
    {
        return match ($this) {
            self::Check1Running => 'Semakan AIRS sedang dijalankan',
            self::AwaitingApplicant => 'Keputusan semakan sedia — tindakan anda diperlukan',
            self::Check2Running => 'Semakan semula AIRS sedang dijalankan',
            self::HandedOver => 'Diserahkan kepada Jabatan Kejuruteraan',
        };
    }
}
