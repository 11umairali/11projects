<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeBackNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $firstName = $notifiable->first_name;

        return (new MailMessage())
            ->subject("Ready for day 2, {$firstName}?")
            ->greeting("Welcome back, {$firstName}!")
            ->line('Here\'s what you can do today to get the most out of ' . config('app.name') . ':')
            ->line('**1. Train your AI** -- Upload documents to your Knowledge Base so AI replies match your company voice.')
            ->line('**2. Set up workflows** -- Automate repetitive tasks like follow-up emails and lead assignment.')
            ->line('**3. Connect more channels** -- Add WhatsApp, Telegram, or Slack to manage all conversations in one inbox.')
            ->action('Continue Where You Left Off', config('app.url') . '/dashboard')
            ->line('Questions? Reply to this email -- we read every message.')
            ->salutation('The ' . config('app.name') . ' Team');
    }
}
