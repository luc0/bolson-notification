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
            ->with('firstKnownPrice')
            ->where('property_use', $this->propertyUse())
            ->whereBetween('created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->lazy()
            ->map(function (Rental $rental) {
                // The price it was published with: later changes belong to later months, not to this one.
                $price = $rental->firstKnownPrice ?? $rental;
                if ($price->price_amount === null) {
                    return null;
                }

                return new PricedListing(
                    amount: (float) $price->price_amount,
                    currency: Currency::from($price->price_currency),
                    city: $rental->city,
                    bedrooms: $rental->bedrooms,
                );
            })
            ->filter();
    }

    public function firstPublishedAt(): ?CarbonImmutable
    {
        $oldest = Rental::where('property_use', $this->propertyUse())->min('created_at');

        return $oldest === null ? null : CarbonImmutable::parse($oldest);
    }
}
