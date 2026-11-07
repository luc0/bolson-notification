<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Models\Rental;
use App\Domains\Rentals\Support\CityResolver;
use App\Domains\Sources\Data\Source;

/**
 * Sets a rental's city and neighborhood: from its own texts first, then the city it already had
 * (e.g. found on its detail page), and the source's default city only as a last resort.
 */
class RentalLocator
{
    public function __construct(private readonly CityResolver $cityResolver) {}

    public function locate(Rental $rental, ?Source $source): void
    {
        $resolved = $this->cityResolver->resolve([$rental->location, $rental->content]);
        $sourceDefaultCity = City::tryFrom((string) $source?->defaultCity);

        $rental->update([
            'city' => $resolved->city ?? $rental->city ?? $sourceDefaultCity,
            'neighborhood' => $resolved->neighborhood ?? $rental->neighborhood,
        ]);
    }
}
