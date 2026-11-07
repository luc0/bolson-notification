<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Locations\Enums\City;
use App\Domains\Locations\Support\CityResolver;
use App\Domains\Rentals\Models\Rental;
use App\Domains\Rentals\Support\PropertyUseResolver;
use App\Domains\Sources\Data\Source;

/**
 * Sets what the filters use (city, neighborhood, property use) from the rental's own texts first,
 * then the value it already had (e.g. found on its detail page), and for the city the source's
 * default only as a last resort.
 */
class RentalClassifier
{
    public function __construct(
        private readonly CityResolver $cityResolver,
        private readonly PropertyUseResolver $propertyUseResolver,
    ) {}

    public function classify(Rental $rental, ?Source $source): void
    {
        $location = $this->cityResolver->resolve([$rental->location, $rental->content]);
        $sourceDefaultCity = City::tryFrom((string) $source?->defaultCity);
        $propertyUse = $this->propertyUseResolver->resolve([$rental->content, $rental->description]);

        $rental->update([
            'city' => $location->city ?? $rental->city ?? $sourceDefaultCity,
            'neighborhood' => $location->neighborhood ?? $rental->neighborhood,
            'property_use' => $propertyUse ?? $rental->property_use,
        ]);
    }
}
