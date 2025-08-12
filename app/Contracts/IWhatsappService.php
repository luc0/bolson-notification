<?php

namespace App\Contracts;

interface IWhatsappService
{
    public function sendMessage(array $allItems);
}
