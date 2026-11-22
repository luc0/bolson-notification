<?php

namespace App\Domains\Market\Enums;

/**
 * What a series measures: the listing price, the price per m² (lands, so big and small plots compare)
 * or the price per built m² (houses).
 */
enum PriceUnit: string
{
    case Price = 'price';
    case PricePerSquareMeter = 'price_per_m2';
    case PricePerCoveredSquareMeter = 'price_per_built_m2';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Precio',
            self::PricePerSquareMeter => 'Precio por m²',
            self::PricePerCoveredSquareMeter => 'Precio por m² cubierto',
        };
    }
}
