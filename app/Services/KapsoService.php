<?php

namespace App\Services;

use App\Contracts\IWhatsapp;
use Illuminate\Support\Facades\Log;

class KapsoService implements IWhatsapp
{
    public function sendMessage(array $allItems)
    {
        Log::info('Utilizando Kapso para envio de mensajes.');
        // TODO: Implement sendMessage() method.
    }
}
