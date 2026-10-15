<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = explode(' ', $notifiable->name ?? 'there')[0];

        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name') . '!')
            ->greeting("Hi {$firstName},")
            ->line('Your Dahimail account and email address are ready to use.')
            ->line('Open your inbox to send email, or add friends to start a chat or call.')
            ->action('Open Dahimail', url('/dashboard'))
            ->line('Keep your recovery phrase somewhere safe. You may need it if you forget your password.')
            ->salutation('— The ' . config('app.name') . ' Team');
    }
}
