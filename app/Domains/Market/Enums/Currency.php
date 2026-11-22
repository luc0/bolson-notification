<?php

namespace App\Domains\Market\Enums;

enum Currency: string
{
    case Ars = 'ARS';
    case Usd = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::Ars => 'Pesos',
            self::Usd => 'Dólares',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Ars => '$',
            self::Usd => 'U$S ',
        };
    }
}
