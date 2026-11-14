<?php

namespace App\Console\Commands;

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\WebPushChannel;

class SendPushTest extends Command
{
    protected $signature = 'push:send-test
        {phone : Phone of the member, as registered}
        {message=🔔 Notificación de prueba de Techito. Si la ves, las notificaciones funcionan. : Text to send}';

    protected $description = 'Sends a test web push to every browser of the member with this phone (needs "Activar notificaciones" tapped in the app).';

    public function handle(): int
    {
        if (blank(config('webpush.vapid.public_key')) || blank(config('webpush.vapid.private_key'))) {
            $this->error('Web push is not configured (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY).');

            return self::FAILURE;
        }

        $user = User::where('phone', $this->argument('phone'))->first();
        if ($user === null) {
            $this->error("No member with phone {$this->argument('phone')}.");

            return self::FAILURE;
        }

        $subscriber = Subscriber::forUser($user);
        $browserCount = $subscriber->pushSubscriptions()->count();
        if ($browserCount === 0) {
            $this->error('This member has no browser subscribed: they have to tap "Activar notificaciones" in the app first.');

            return self::FAILURE;
        }

        // The channel reports each browser: listen to tell which ones got it.
        $sentCount = 0;
        $failures = [];
        Event::listen(NotificationSent::class, function () use (&$sentCount) {
            $sentCount++;
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $event) use (&$failures) {
            $expired = $event->report->isSubscriptionExpired() ? ' (expired, removed)' : '';
            $failures[] = "{$event->report->getReason()}{$expired}";
        });

        $notification = new NewRentalsNotification(
            title: 'Prueba',
            body: $this->argument('message'),
            url: url('/'),
            icon: '/icons/icon-192.png',
        );
        Notification::sendNow($subscriber, $notification, [WebPushChannel::class]);

        $this->info("Sent to {$sentCount} of {$browserCount} browsers of {$user->phone}.");
        foreach ($failures as $failure) {
            $this->warn("Failed: {$failure}");
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
