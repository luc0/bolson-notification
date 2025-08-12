<?php

namespace App\Services;

use App\Contracts\IWhatsappService;
use Illuminate\Support\Facades\Log;

class KapsoService implements IWhatsappService
{
    public function sendMessage(array $allItems)
    {
        Log::info('Utilizando Kapso para envio de mensajes.');
        // TODO: Implement sendMessage() method.
    }
}
