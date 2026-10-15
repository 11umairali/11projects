<?php
namespace App\Notifications;
use App\Models\AdminBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class AdminBroadcastNotification extends Notification {
    public function __construct(public AdminBroadcast $broadcast, public string $channel) {}
    public function via(object $notifiable): array { return [$this->channel]; }
    public function toArray(object $notifiable): array {
        return ['type' => 'announcement_'.$this->broadcast->category, 'title' => $this->broadcast->renderFor($notifiable, $this->broadcast->title),
            'body' => $this->broadcast->renderFor($notifiable, $this->broadcast->body), 'icon' => 'bell',
            'action_url' => $this->broadcast->action_url, 'sender_name' => config('app.name')];
    }
    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)->subject($this->broadcast->renderFor($notifiable, $this->broadcast->title))
            ->view('emails.admin-broadcast', ['broadcast' => $this->broadcast, 'recipient' => $notifiable]);
    }
}
