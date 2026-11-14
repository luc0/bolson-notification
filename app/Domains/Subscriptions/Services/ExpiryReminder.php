<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Notifications\SubscriptionExpiringNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Warns members whose subscription expires soon, once per subscription period, and only if they
 * opened the app lately: a member that stopped using it is left to expire (that is a signal too).
 */
class ExpiryReminder
{
    /**
     * @return int how many subscribers were reminded.
     */
    public function remindExpiringSoon(): int
    {
        $config = config('subscriptions.expiry_reminder');
        $notification = new SubscriptionExpiringNotification;

        $remindedCount = 0;
        Subscriber::active()
            ->notifiable()
            ->whereNull('expiry_reminder_sent_at')
            ->where('expires_at', '<=', now()->addDays($config['days_before']))
            ->whereHas('user', fn (Builder $query) => $query->where('last_seen_at', '>=', now()->subDays($config['active_within_days'])))
            ->cursor()
            ->each(function (Subscriber $subscriber) use ($notification, &$remindedCount) {
                $subscriber->notify($notification);
                $subscriber->update(['expiry_reminder_sent_at' => now()]);
                $remindedCount++;
            });

        Log::info("⏳ Expiry reminders sent to {$remindedCount} subscribers");

        return $remindedCount;
    }
}
