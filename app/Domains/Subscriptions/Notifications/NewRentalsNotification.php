<?php

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\Subscriptions\Channels\TelegramChannel;
use App\Domains\Subscriptions\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewRentalsNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $icon
    ) {}

    /**
     * Only the channels the subscriber has.
     */
    public function via(Subscriber $notifiable): array
    {
        return array_keys(array_filter([
            WebPushChannel::class => $notifiable->pushSubscriptions()->exists(),
            TelegramChannel::class => $notifiable->receivesTelegram(),
        ]));
    }

    /**
     * @return array{text: string, button_text: string, button_url: string}
     */
    public function toTelegram(Subscriber $notifiable): array
    {
        return [
            'text' => "🏠 {$this->title}\n{$this->body}\n\n{$this->url}",
            'button_text' => 'Ver alquileres',
            'button_url' => $this->url,
        ];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon)
            ->badge($this->icon)
            ->data(['url' => $this->url])
            ->action('Ver', $this->url);
    }
}
