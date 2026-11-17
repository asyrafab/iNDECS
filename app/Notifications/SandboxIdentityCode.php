<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SandboxIdentityCode extends Notification
{
    public function __construct(public readonly string $code, public readonly string $purpose) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Local implementation must never deliver to a real mail transport.
        return (new MailMessage)->mailer('array')->subject('Kod akses iNDECS sandbox')
            ->line('Kod anda: '.$this->code)->line('Sah selama lima minit. Jangan kongsi kod ini.');
    }
}
