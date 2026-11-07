<?php

namespace App\Domains\Lands\Enums;

enum LandSort: string
{
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case AreaDesc = 'area_desc';

    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Más nuevos',
            self::PriceAsc => 'Menor precio',
            self::PriceDesc => 'Mayor precio',
            self::AreaDesc => 'Más grandes',
        };
    }
}
