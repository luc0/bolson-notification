<?php

namespace App\Console\Commands;

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use Illuminate\Console\Command;

class ExtendSubscription extends Command
{
    protected $signature = 'subscriptions:extend
        {phone : Phone of the member, as registered}
        {--days= : Days to add (default: subscriptions.duration_days)}';

    protected $description = 'Renews the subscription of the member with this phone: adds the days from the current expiry, or from now if it already expired.';

    public function handle(): int
    {
        $user = User::where('phone', $this->argument('phone'))->first();
        if ($user === null) {
            $this->error("No member with phone {$this->argument('phone')}.");

            return self::FAILURE;
        }

        $days = $this->option('days');
        $isInvalidDays = $days !== null && (! ctype_digit((string) $days) || (int) $days === 0);
        if ($isInvalidDays) {
            $this->error('--days must be a positive number.');

            return self::FAILURE;
        }

        $subscriber = Subscriber::forUser($user);
        $subscriber->extend($days === null ? null : (int) $days);

        $this->info("Subscription of {$user->phone} now expires on {$subscriber->expires_at->format('d/m/Y H:i')}.");

        return self::SUCCESS;
    }
}
