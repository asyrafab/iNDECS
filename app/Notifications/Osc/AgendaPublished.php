<?php

declare(strict_types=1);

namespace App\Notifications\Osc;

use App\Models\Osc\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Announces a published agenda. Deliberately carries no paper attachments (design S7). */
final class AgendaPublished extends Notification
{
    public function __construct(public Meeting $meeting, public int $itemCount, public int $version) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Agenda Mesyuarat OSC {$this->meeting->number} diterbitkan (versi {$this->version})")
            ->greeting('Salam sejahtera,')
            ->line("Agenda Mesyuarat OSC {$this->meeting->number} telah diterbitkan dengan {$this->itemCount} perkara.")
            ->action('Buka agenda', url('/osc/meetings/'.$this->meeting->id));
    }
}
