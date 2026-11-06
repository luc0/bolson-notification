<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Models\AnonSubscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use Illuminate\Support\Facades\Log;

/**
 * Sends web push notifications to every subscriber.
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
        Log::info("📱 Push notifications sent to {$notifiedCount} subscribers about {$rentalCount} new rentals");

        return $notifiedCount;
    }

    /**
     * @return int how many subscribers were notified.
     */
    public function notifyAll(NewRentalsNotification $notification): int
    {
        $notifiedCount = 0;
        AnonSubscriber::whereHas('pushSubscriptions')->cursor()->each(function (AnonSubscriber $subscriber) use ($notification, &$notifiedCount) {
            $subscriber->notify($notification);
            $notifiedCount++;
        });

        return $notifiedCount;
    }
}
