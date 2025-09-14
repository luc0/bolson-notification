<?php

namespace App\Services;

use App\Models\AnonSubscriber;
use App\Notifications\NewRentalsNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendNewRentalsNotification(int $rentalCount): int
    {
        if ($rentalCount === 0) {
            return 0;
        }

        $config = config('notifications.new_rentals');
        
        $title = $config['title'];
        $body = str_replace('{count}', $rentalCount, $config['body_template']);
        $url = url($config['url']);
        $icon = $config['icon'];

        $notification = new NewRentalsNotification($title, $body, $url, $icon);

        $notifiedCount = 0;
        AnonSubscriber::whereHas('pushSubscriptions')->cursor()->each(function ($subscriber) use ($notification, &$notifiedCount) {
            $subscriber->notify($notification);
            $notifiedCount++;
        });

        Log::info("📱 Push notifications sent to {$notifiedCount} subscribers about {$rentalCount} new rentals");

        return $notifiedCount;
    }
}
