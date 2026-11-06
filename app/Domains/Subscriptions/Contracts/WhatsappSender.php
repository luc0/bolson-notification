<?php

namespace App\Domains\Subscriptions\Contracts;

interface WhatsappSender
{
    public function sendMessage(array $allItems);
}
