<?php

namespace App\Contracts;

interface WhatsappSender
{
    public function sendMessage(array $allItems);
}
