<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PublicAccessLink extends Notification
{
    public function __construct(public string $token, public string $purpose) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = match ($this->purpose) {
            'verify' => 'Sahkan emel iNDECS',
            'reset' => 'Tetapkan semula kata laluan iNDECS',
            default => 'Pautan akses iNDECS',
        };

        return (new MailMessage)->subject($title)->greeting('Salam sejahtera,')
            ->line('Pautan ini sah selama 15 minit dan boleh digunakan sekali sahaja.')
            ->action($title, route('portal.link.show', ['purpose' => $this->purpose, 'token' => $this->token]))
            ->line('Abaikan emel ini jika anda tidak membuat permintaan ini.');
    }
}
