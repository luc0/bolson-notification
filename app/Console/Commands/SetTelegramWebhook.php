<?php

namespace App\Console\Commands;

use App\Integrations\Telegram\TelegramClient;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Tells Telegram to send the bot messages to this app (APP_URL). Run it again whenever APP_URL changes.';

    public function handle(TelegramClient $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->warn('Telegram is not configured (TELEGRAM_BOT_TOKEN / TELEGRAM_BOT_USERNAME): skipped.');

            return self::SUCCESS;
        }

        $url = route('telegram.webhook');
        $isPublicHttps = $telegram->isPublicHttpsUrl($url);
        if (! $isPublicHttps) {
            $this->error("Telegram only calls public https URLs and APP_URL gives {$url}. Use ./scripts/switch-url.sh ngrok locally.");

            return self::FAILURE;
        }

        $response = $telegram->setWebhook($url, config('services.telegram.webhook_secret'));
        if (! $response->successful()) {
            $this->error('Telegram rejected the webhook: '.$response->body());

            return self::FAILURE;
        }

        $this->info("Webhook set: {$url}");

        return self::SUCCESS;
    }
}
