<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewRental extends Notification
{
    use Queueable;

    public function __construct(public $rental) {}

    public function via($notifiable)
    {
        // decimos que se envíe por webpush
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Nuevo alquiler')
            ->body($this->rental->title . ' — ' . $this->rental->price)
            ->icon('/icons/icon-icon-192.png') // asegurate que exista
            ->badge('/icons/icon-icon-192.png')
            ->data([
                'url' => route('rentals.show', $this->rental),
            ])
            ->action('Ver', route('rentals.show', $this->rental));
    }
}
