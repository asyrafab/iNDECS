<?php

namespace App\Enums\Osc;

enum MeetingRole: string
{
    case Chair = 'chair';
    case Secretary = 'secretary';
    case Steno = 'steno';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Chair => 'Pengerusi',
            self::Secretary => 'Setiausaha',
            self::Steno => 'STENO',
            self::Member => 'Ahli',
        };
    }
}
