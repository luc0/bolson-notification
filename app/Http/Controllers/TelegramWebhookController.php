<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Models\Subscriber;
use App\Integrations\Telegram\TelegramClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Telegram calls this for every message the bot receives (registered with `telegram:set-webhook`).
 * "/start <code>" comes from the "Conectar Telegram" link of the profile and links the chat to that subscriber.
 * Always answers 200 (except for a wrong secret): otherwise Telegram keeps retrying the same update.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramClient $telegram): Response
    {
        $hasValidSecret = hash_equals((string) config('services.telegram.webhook_secret'), (string) $request->header('X-Telegram-Bot-Api-Secret-Token'));
        abort_unless($hasValidSecret, HttpStatus::HTTP_FORBIDDEN);

        $chatId = $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text'));
        $isStartCommand = $chatId !== null && str_starts_with($text, '/start');
        if (! $isStartCommand) {
            return response()->noContent(HttpStatus::HTTP_OK);
        }

        $linkToken = trim(substr($text, strlen('/start')));
        $subscriber = $linkToken === '' ? null : Subscriber::where('telegram_link_token', $linkToken)->first();

        if ($subscriber === null) {
            $telegram->sendMessage((string) $chatId, 'Para recibir los avisos, conectá Telegram desde tu perfil: '.route('profile.edit'));

            return response()->noContent(HttpStatus::HTTP_OK);
        }

        $subscriber->linkTelegramChat((string) $chatId);
        $telegram->sendMessage((string) $chatId, '✅ Listo, te vamos a avisar por acá cuando haya alquileres nuevos. Podés apagarlo desde tu perfil.');

        return response()->noContent(HttpStatus::HTTP_OK);
    }
}
