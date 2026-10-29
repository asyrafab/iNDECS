<?php

namespace App\Enums\Osc;

enum TablingPurpose: string
{
    case Permohonan = 'permohonan';
    case Rayuan = 'rayuan';
    case PertimbanganSemula = 'pertimbangan_semula';
    case Pindaan = 'pindaan';
    case LainLain = 'lain_lain';

    public function label(): string
    {
        return match ($this) {
            self::Permohonan => 'Permohonan',
            self::Rayuan => 'Rayuan',
            self::PertimbanganSemula => 'Pertimbangan semula',
            self::Pindaan => 'Pindaan',
            self::LainLain => 'Lain-lain',
        };
    }
}
