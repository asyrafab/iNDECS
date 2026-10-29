<?php

namespace App\Enums\Osc;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case AgendaPublished = 'agenda_published';
    case InProgress = 'in_progress';
    case Closed = 'closed';
    case MinutesConfirmed = 'minutes_confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::AgendaPublished => 'Agenda diterbitkan',
            self::InProgress => 'Sedang berlangsung',
            self::Closed => 'Selesai',
            self::MinutesConfirmed => 'Minit disahkan',
        };
    }
}
