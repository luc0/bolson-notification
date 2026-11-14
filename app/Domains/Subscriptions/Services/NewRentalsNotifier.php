<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use Illuminate\Support\Facades\Log;

/**
 * Notifies every active subscriber by their channels (web push and/or Telegram, see NewRentalsNotification::via()).
 */
class NewRentalsNotifier
{
    /**
     * @return int how many subscribers were notified.
     */
    public function notifyNewRentals(int $rentalCount): int
    {
        if ($rentalCount === 0) {
            return 0;
        }

        $config = config('notifications.new_rentals');
        $notification = new NewRentalsNotification(
            title: $config['title'],
            body: str_replace('{count}', (string) $rentalCount, $config['body_template']),
            url: url($config['url']),
            icon: $config['icon'],
        );

        $notifiedCount = $this->notifyAll($notification);
        Log::info("📱 Notifications sent to {$notifiedCount} subscribers about {$rentalCount} new rentals");

        return $notifiedCount;
    }

    /**
     * @return int how many subscribers were notified.
     */
    public function notifyAll(NewRentalsNotification $notification): int
    {
        $notifiedCount = 0;
        Subscriber::active()->notifiable()->cursor()->each(function (Subscriber $subscriber) use ($notification, &$notifiedCount) {
            $subscriber->notify($notification);
            $notifiedCount++;
        });

        return $notifiedCount;
    }
}
