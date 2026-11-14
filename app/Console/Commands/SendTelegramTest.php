<?php

namespace App\Console\Commands;

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Integrations\Telegram\TelegramClient;
use Illuminate\Console\Command;

class SendTelegramTest extends Command
{
    protected $signature = 'telegram:send-test
        {phone : Phone of the member, as registered}
        {message=🔔 Mensaje de prueba de Techito. Si lo ves, los avisos por Telegram funcionan. : Text to send}';

    protected $description = 'Sends a test Telegram message to the member with this phone (needs the chat linked from the profile).';

    public function handle(TelegramClient $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('Telegram is not configured (TELEGRAM_BOT_TOKEN / TELEGRAM_BOT_USERNAME).');

            return self::FAILURE;
        }

        $user = User::where('phone', $this->argument('phone'))->first();
        if ($user === null) {
            $this->error("No member with phone {$this->argument('phone')}.");

            return self::FAILURE;
        }

        $chatId = Subscriber::where('user_id', $user->id)->value('telegram_chat_id');
        if ($chatId === null) {
            $this->error('This member has not linked Telegram: they have to tap "Conectar Telegram" in the profile first.');

            return self::FAILURE;
        }

        $response = $telegram->sendMessage($chatId, $this->argument('message'));
        if (! $response->successful()) {
            $this->error('Telegram rejected the message: '.$response->body());

            return self::FAILURE;
        }

        $this->info("Sent to {$user->phone} (chat {$chatId}).");

        return self::SUCCESS;
    }
}
