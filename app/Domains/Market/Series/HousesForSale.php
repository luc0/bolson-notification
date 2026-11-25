<?php

namespace App\Domains\Market\Series;

use App\Domains\Houses\Models\House;
use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Data\PricedListing;
use App\Domains\Market\Data\SizeRanges;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Enums\PriceUnit;
use Carbon\CarbonImmutable;

/**
 * Price per built m² of houses for sale, in dollars, so small and big houses compare.
 * Houses without a known built area are left out.
 */
class HousesForSale implements PriceSeries
{
    public function key(): string
    {
        return 'houses_for_sale';
    }

    public function label(): string
    {
        return 'Casas en venta';
    }

    public function unit(): PriceUnit
    {
        return PriceUnit::PricePerCoveredSquareMeter;
    }

    public function currencies(): array
    {
        return [Currency::Usd];
    }

    /**
     * Ready for a bedrooms filter: return true and run market:snapshot --backfill to store the stats per bedroom count.
     */
    public function groupsByBedrooms(): bool
    {
        return false;
    }

    /**
     * Bigger houses are cheaper per m², so places are compared by built area.
     */
    public function sizeRanges(): ?SizeRanges
    {
        return SizeRanges::fromBounds('Superficie cubierta', config('market.size_ranges.'.$this->key()));
    }

    public function listingsPublishedIn(CarbonImmutable $month): iterable
    {
        return House::query()
            ->with('firstKnownPrice')
            ->where('covered_area', '>', 0)
            ->whereBetween('created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->lazy()
            ->map(function (House $house) {
                // The price it was published with: later changes belong to later months, not to this one.
                $price = $house->firstKnownPrice ?? $house;
                if ($price->price_amount === null) {
                    return null;
                }

                return new PricedListing(
                    amount: (float) $price->price_amount / $house->covered_area,
                    currency: Currency::from($price->price_currency),
                    city: $house->city,
                    bedrooms: $house->bedrooms,
                    size: $house->covered_area,
                );
            })
            ->filter();
    }

    public function firstPublishedAt(): ?CarbonImmutable
    {
        $oldest = House::min('created_at');

        return $oldest === null ? null : CarbonImmutable::parse($oldest);
    }
}
