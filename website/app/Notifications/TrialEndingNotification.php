<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your plan';
        $trialEndsAt = $this->subscription->trial_ends_at;
        $daysRemaining = $trialEndsAt ? (int) now()->diffInDays($trialEndsAt) : 3;

        return (new MailMessage())
            ->subject("Your {$planName} trial ends in {$daysRemaining} days")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("Your free trial of the **{$planName}** plan will end in **{$daysRemaining} days**.")
            ->line('After the trial ends, your workspace will be downgraded to the free plan unless you add a payment method.')
            ->line('To continue enjoying all premium features without interruption:')
            ->action('Add Payment Method', config('app.url') . '/settings/billing')
            ->line('If you have any questions, feel free to reach out to our support team.')
            ->salutation('The ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trial_ending',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan?->name,
            'trial_ends_at' => $this->subscription->trial_ends_at?->toDateTimeString(),
            'message' => 'Your trial is ending soon. Add a payment method to continue.',
        ];
    }
}
