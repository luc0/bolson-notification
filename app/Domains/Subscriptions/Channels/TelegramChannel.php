<?php

namespace App\Domains\Subscriptions\Channels;

use App\Domains\Subscriptions\Models\Subscriber;
use App\Integrations\Telegram\TelegramClient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a notification's toTelegram() message to the subscriber's linked chat. A failure is logged
 * and never stops the other channels or subscribers.
 */
class TelegramChannel
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function send(Subscriber $subscriber, Notification $notification): void
    {
        if (! $subscriber->receivesTelegram()) {
            return;
        }

        /** @var array{text: string, button_text?: string, button_url?: string} $message */
        $message = $notification->toTelegram($subscriber);
        $response = $this->telegram->sendMessage(
            $subscriber->telegram_chat_id,
            $message['text'],
            $message['button_text'] ?? null,
            $message['button_url'] ?? null,
        );

        if ($response->successful()) {
            return;
        }

        // 403: the user blocked the bot or deleted the chat. Unlink it so we stop trying.
        $isChatGone = $response->status() === Response::HTTP_FORBIDDEN;
        if ($isChatGone) {
            $subscriber->update(['telegram_chat_id' => null]);
        }

        Log::warning("⚠️ Telegram message failed for subscriber {$subscriber->id}", ['status' => $response->status(), 'body' => $response->body()]);
    }
}
