<?php

namespace App\Domains\Market\Enums;

/**
 * What a series measures: the listing price, or the price per m² (lands, so big and small plots compare).
 */
enum PriceUnit: string
{
    case Price = 'price';
    case PricePerSquareMeter = 'price_per_m2';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Precio',
            self::PricePerSquareMeter => 'Precio por m²',
        };
    }
}
