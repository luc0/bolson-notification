<?php

namespace App\Console\Commands;

use App\Domains\Subscriptions\Services\ExpiryReminder;
use Illuminate\Console\Command;

class SendExpiryReminders extends Command
{
    protected $signature = 'subscriptions:remind-expiring';

    protected $description = 'Warns active members (Telegram / push) that their subscription expires soon.';

    public function handle(ExpiryReminder $reminder): int
    {
        $count = $reminder->remindExpiringSoon();
        $this->info("Reminded {$count} subscribers.");

        return self::SUCCESS;
    }
}
