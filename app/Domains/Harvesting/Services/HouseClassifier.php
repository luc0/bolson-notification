<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Houses\Models\House;
use App\Domains\Locations\Enums\City;
use App\Domains\Locations\Support\CityResolver;
use App\Domains\Sources\Data\Source;

/**
 * Sets what the filters use (city, neighborhood) from the house's own texts first, then the value
 * it already had, and the source's default city only as a last resort.
 */
class HouseClassifier
{
    public function __construct(private readonly CityResolver $cityResolver) {}

    public function classify(House $house, ?Source $source): void
    {
        $location = $this->cityResolver->resolve([$house->location, $house->content]);
        $sourceDefaultCity = City::tryFrom((string) $source?->defaultCity);

        $house->update([
            'city' => $location->city ?? $house->city ?? $sourceDefaultCity,
            'neighborhood' => $location->neighborhood ?? $house->neighborhood,
        ]);
    }
}
