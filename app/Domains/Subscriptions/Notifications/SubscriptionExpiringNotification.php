<?php

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\Subscriptions\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Reminds an active member that their subscription expires soon (ExpiryReminder).
 */
class SubscriptionExpiringNotification extends Notification
{
    use Queueable;

    public function via(Subscriber $notifiable): array
    {
        return $notifiable->notificationChannels();
    }

    /**
     * @return array{text: string, button_text: string, button_url: string}
     */
    public function toTelegram(Subscriber $notifiable): array
    {
        return [
            'text' => "⏳ {$this->title()}\n{$this->body($notifiable)}",
            'button_text' => 'Ver mi suscripción',
            'button_url' => $this->url(),
        ];
    }

    public function toWebPush(Subscriber $notifiable, $notification): WebPushMessage
    {
        $icon = config('notifications.expiry_reminder.icon');

        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body($notifiable))
            ->icon($icon)
            ->badge($icon)
            ->data(['url' => $this->url()])
            ->action('Ver', $this->url());
    }

    private function title(): string
    {
        return config('notifications.expiry_reminder.title');
    }

    private function body(Subscriber $notifiable): string
    {
        return str_replace('{date}', $notifiable->expires_at->format('d/m'), config('notifications.expiry_reminder.body_template'));
    }

    private function url(): string
    {
        return url(config('notifications.expiry_reminder.url'));
    }
}
