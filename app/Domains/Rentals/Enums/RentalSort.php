<?php

namespace App\Domains\Rentals\Enums;

enum RentalSort: string
{
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';

    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Más nuevos',
            self::PriceAsc => 'Menor precio',
            self::PriceDesc => 'Mayor precio',
        };
    }
}
