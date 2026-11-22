<?php

namespace App\Domains\Market\Series;

use App\Domains\Rentals\Enums\PropertyUse;

/**
 * Rentals to live in. Rentals whose use couldn't be resolved are left out, like in the listing.
 */
class ResidentialRentals extends RentalSeries
{
    public function key(): string
    {
        return 'residential_rentals';
    }

    public function label(): string
    {
        return 'Alquileres para vivir';
    }

    protected function propertyUse(): PropertyUse
    {
        return PropertyUse::Residential;
    }
}
