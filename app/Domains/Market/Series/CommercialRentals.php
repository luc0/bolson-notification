<?php

namespace App\Domains\Market\Series;

use App\Domains\Rentals\Enums\PropertyUse;

/**
 * Shops, offices and other commercial rentals.
 */
class CommercialRentals extends RentalSeries
{
    public function key(): string
    {
        return 'commercial_rentals';
    }

    public function label(): string
    {
        return 'Alquileres comerciales';
    }

    protected function propertyUse(): PropertyUse
    {
        return PropertyUse::Commercial;
    }
}
