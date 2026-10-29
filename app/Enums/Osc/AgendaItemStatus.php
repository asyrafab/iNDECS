<?php

namespace App\Enums\Osc;

enum AgendaItemStatus: string
{
    case Proposed = 'proposed';
    case Endorsed = 'endorsed';
    case Accepted = 'accepted';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Dicadangkan',
            self::Endorsed => 'Disokong ketua jabatan',
            self::Accepted => 'Dalam agenda',
            self::Returned => 'Dikembalikan',
        };
    }
}
