<?php

namespace App\Console\Commands;

use App\Integrations\Telegram\TelegramClient;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--force : Replace the webhook even if it points to a deployed app}';

    protected $description = 'Tells Telegram to send the bot messages to this app (APP_URL). Run it again whenever APP_URL changes.';

    /**
     * Dev tunnel hosts: outside production, only a webhook on one of these can be replaced.
     */
    private const TUNNEL_HOST_SUFFIXES = ['.ngrok-free.app', '.ngrok-free.dev', '.ngrok.app', '.ngrok.io'];

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

        // A bot has a single webhook: from local, taking over the bot of a deployed app breaks it there.
        $currentUrl = $this->option('force') || app()->isProduction() ? '' : $telegram->webhookUrl();
        $wouldStealDeployedBot = $currentUrl !== '' && $currentUrl !== $url && ! $this->isTunnelUrl($currentUrl);
        if ($wouldStealDeployedBot) {
            $this->error("This bot belongs to a deployed app (its webhook is {$currentUrl}). Use a separate bot for local (TELEGRAM_BOT_TOKEN / TELEGRAM_BOT_USERNAME in .env), or --force.");

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

    private function isTunnelUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return collect(self::TUNNEL_HOST_SUFFIXES)->contains(fn (string $suffix) => str_ends_with($host, $suffix));
    }
}
