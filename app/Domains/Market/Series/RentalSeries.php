<?php

namespace App\Domains\Market\Series;

use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Data\PricedListing;
use App\Domains\Market\Data\SizeRanges;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Enums\PriceUnit;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Models\Rental;
use Carbon\CarbonImmutable;

/**
 * Monthly rent of the rentals of one property use, in pesos and dollars.
 */
abstract class RentalSeries implements PriceSeries
{
    abstract protected function propertyUse(): PropertyUse;

    public function unit(): PriceUnit
    {
        return PriceUnit::Price;
    }

    public function currencies(): array
    {
        return [Currency::Ars, Currency::Usd];
    }

    public function groupsByBedrooms(): bool
    {
        return false;
    }

    public function sizeRanges(): ?SizeRanges
    {
        return null;
    }

    public function listingsPublishedIn(CarbonImmutable $month): iterable
    {
        return Rental::query()
            ->where('property_use', $this->propertyUse())
            ->whereNotNull('price_amount')
            ->whereBetween('created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->lazy()
            ->map(fn (Rental $rental) => new PricedListing(
                amount: (float) $rental->price_amount,
                currency: Currency::from($rental->price_currency),
                city: $rental->city,
                bedrooms: $rental->bedrooms,
            ));
    }

    public function firstPublishedAt(): ?CarbonImmutable
    {
        $oldest = Rental::where('property_use', $this->propertyUse())->min('created_at');

        return $oldest === null ? null : CarbonImmutable::parse($oldest);
    }
}
