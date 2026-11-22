<?php

namespace App\Domains\Market\Data;

use App\Domains\Locations\Enums\City;
use App\Domains\Market\Enums\Currency;

/**
 * What the stats need from a listing: its price (already per m² for lands) and how to group it
 * (size: m² used for the series size ranges).
 */
final readonly class PricedListing
{
    public function __construct(
        public float $amount,
        public Currency $currency,
        public ?City $city = null,
        public ?int $bedrooms = null,
        public ?float $size = null,
    ) {}
}
