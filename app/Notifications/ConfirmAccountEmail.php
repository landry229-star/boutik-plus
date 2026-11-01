<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmAccountEmail extends Notification
{
    public function __construct(private string $confirmationUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmez votre nouvelle adresse e-mail')
            ->greeting('Bonjour,')
            ->line('Une demande de changement d’adresse e-mail a été faite pour votre compte Boutik Plus.')
            ->action('Confirmer cette adresse', $this->confirmationUrl)
            ->line('Ce lien expirera dans 30 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.');
    }
}
