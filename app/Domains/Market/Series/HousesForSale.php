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
 * Sale price of houses, in dollars.
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
        return PriceUnit::Price;
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
     * By built m² (houses without it only count in every size).
     */
    public function sizeRanges(): ?SizeRanges
    {
        return SizeRanges::fromBounds('Superficie cubierta', config('market.size_ranges.'.$this->key()));
    }

    public function listingsPublishedIn(CarbonImmutable $month): iterable
    {
        return House::query()
            ->whereNotNull('price_amount')
            ->whereBetween('created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->lazy()
            ->map(fn (House $house) => new PricedListing(
                amount: (float) $house->price_amount,
                currency: Currency::from($house->price_currency),
                city: $house->city,
                bedrooms: $house->bedrooms,
                size: $house->covered_area,
            ));
    }

    public function firstPublishedAt(): ?CarbonImmutable
    {
        $oldest = House::min('created_at');

        return $oldest === null ? null : CarbonImmutable::parse($oldest);
    }
}
