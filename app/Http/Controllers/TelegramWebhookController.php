<?php

namespace App\Http\Controllers;

use App\Domains\Members\Actions\RegisterMember;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Integrations\Telegram\TelegramClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Telegram calls this for every message the bot receives (registered with `telegram:set-webhook`).
 * - "/start <code>" comes from the "Conectar Telegram" link of the profile and links the chat to that subscriber.
 * - "/registrar <phone>" (admins only, from their linked chat) registers and subscribes a member.
 * Always answers 200 (except for a wrong secret): otherwise Telegram keeps retrying the same update.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramClient $telegram, RegisterMember $registerMember): Response
    {
        $hasValidSecret = hash_equals((string) config('services.telegram.webhook_secret'), (string) $request->header('X-Telegram-Bot-Api-Secret-Token'));
        abort_unless($hasValidSecret, HttpStatus::HTTP_FORBIDDEN);

        $chatId = $request->input('message.chat.id');
        if ($chatId === null) {
            return response()->noContent(HttpStatus::HTTP_OK);
        }

        $text = trim((string) $request->input('message.text'));
        $command = strtok($text, ' ');
        $argument = trim(substr($text, strlen((string) $command)));

        match ($command) {
            '/start' => $this->linkChat($telegram, (string) $chatId, $argument),
            '/registrar' => $this->registerMember($telegram, $registerMember, (string) $chatId, $argument),
            default => null,
        };

        return response()->noContent(HttpStatus::HTTP_OK);
    }

    private function linkChat(TelegramClient $telegram, string $chatId, string $linkToken): void
    {
        $subscriber = $linkToken === '' ? null : Subscriber::where('telegram_link_token', $linkToken)->first();
        if ($subscriber === null) {
            $telegram->sendMessage($chatId, 'Para recibir los avisos, conectá Telegram desde tu perfil: '.route('profile.edit'));

            return;
        }

        $subscriber->linkTelegramChat($chatId);
        $telegram->sendMessage($chatId, '✅ Listo, te vamos a avisar por acá cuando haya alquileres nuevos. Podés apagarlo desde tu perfil.');
    }

    /**
     * Only from the chat an admin linked from their profile; any other chat is ignored without an answer.
     */
    private function registerMember(TelegramClient $telegram, RegisterMember $registerMember, string $chatId, string $phone): void
    {
        $isAdminChat = Subscriber::where('telegram_chat_id', $chatId)->first()?->user?->isAdmin() ?? false;
        if (! $isAdminChat) {
            return;
        }

        if ($phone === '') {
            $telegram->sendMessage($chatId, 'Pasame el celular, por ejemplo: /registrar 2944123456');

            return;
        }

        $password = (string) config('members.initial_password');
        try {
            $user = $registerMember->handle($phone, $password);
        } catch (ValidationException $exception) {
            $isAlreadyRegistered = isset($exception->validator->failed()['phone']['Unique']);
            $reason = $isAlreadyRegistered ? 'ese número ya está registrado.' : 'el número no es válido (máximo 20 caracteres).';
            $telegram->sendMessage($chatId, "❌ No se pudo registrar {$phone}: {$reason}");

            return;
        }

        $expiresAt = Subscriber::forUser($user)->expires_at->format('d/m/Y');
        $telegram->sendMessage($chatId, "✅ Usuario {$user->phone} creado y suscripto hasta el {$expiresAt}.\nClave: {$password} (que la cambie desde el perfil).\nEntra en ".route('login'));
    }
}
