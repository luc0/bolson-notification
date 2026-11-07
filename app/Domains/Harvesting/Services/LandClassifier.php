<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Lands\Models\Land;
use App\Domains\Locations\Enums\City;
use App\Domains\Locations\Support\CityResolver;
use App\Domains\Sources\Data\Source;

/**
 * Sets what the filters use (city, neighborhood) from the land's own texts first, then the value
 * it already had, and the source's default city only as a last resort.
 */
class LandClassifier
{
    public function __construct(private readonly CityResolver $cityResolver) {}

    public function classify(Land $land, ?Source $source): void
    {
        $location = $this->cityResolver->resolve([$land->location, $land->content]);
        $sourceDefaultCity = City::tryFrom((string) $source?->defaultCity);

        $land->update([
            'city' => $location->city ?? $land->city ?? $sourceDefaultCity,
            'neighborhood' => $location->neighborhood ?? $land->neighborhood,
        ]);
    }
}
