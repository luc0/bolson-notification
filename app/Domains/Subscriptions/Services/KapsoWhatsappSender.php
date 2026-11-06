<?php

namespace App\Domains\Subscriptions\Services;

use App\Domains\Subscriptions\Contracts\WhatsappSender;
use Illuminate\Support\Facades\Log;

class KapsoWhatsappSender implements WhatsappSender
{
    public function sendMessage(array $allItems)
    {
        Log::info('Utilizando Kapso para envio de mensajes.');
        // TODO: Implement sendMessage() method.
    }
}
