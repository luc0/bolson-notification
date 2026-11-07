<?php

namespace App\Integrations\Telegram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Bot API (https://core.telegram.org/bots/api), token from config('services.telegram.bot_token').
 */
class TelegramClient
{
    private const API_URL = 'https://api.telegram.org/bot';

    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.bot_username'));
    }

    /**
     * "https://t.me/<bot>?start=<payload>": opens the bot and sends "/start <payload>" when the user taps Start.
     */
    public function startLink(string $payload): string
    {
        return 'https://t.me/'.config('services.telegram.bot_username').'?start='.$payload;
    }

    /**
     * @param  string|null  $buttonUrl  adds a button that opens this URL, only if it's public https: Telegram rejects
     *                                  the whole message otherwise (e.g. "https://localhost").
     */
    public function sendMessage(string $chatId, string $text, ?string $buttonText = null, ?string $buttonUrl = null): Response
    {
        $hasButton = $buttonText !== null && $buttonUrl !== null && $this->isPublicHttpsUrl($buttonUrl);

        return $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $hasButton ? ['inline_keyboard' => [[['text' => $buttonText, 'url' => $buttonUrl]]]] : null,
        ]));
    }

    /**
     * https with a domain name: not localhost, an IP or a single-label host.
     */
    public function isPublicHttpsUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $isHttps = str_starts_with($url, 'https://');
        $hasDomainName = str_contains($host, '.') && filter_var($host, FILTER_VALIDATE_IP) === false;

        return $isHttps && $hasDomainName && $host !== 'localhost';
    }

    public function setWebhook(string $url, ?string $secret): Response
    {
        return $this->call('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ]));
    }

    private function call(string $method, array $payload): Response
    {
        return Http::timeout(15)->post(self::API_URL.config('services.telegram.bot_token')."/{$method}", $payload);
    }
}
