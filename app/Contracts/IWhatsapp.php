<?php

namespace App\Contracts;

interface IWhatsapp
{
    public function sendMessage(array $allItems);
}
