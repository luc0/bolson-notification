<?php

namespace App\Domains\Market\Series;

use App\Domains\Houses\Models\House;
use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Data\PricedListing;
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
            ));
    }

    public function firstPublishedAt(): ?CarbonImmutable
    {
        $oldest = House::min('created_at');

        return $oldest === null ? null : CarbonImmutable::parse($oldest);
    }
}
