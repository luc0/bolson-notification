<?php

namespace App\Domains\Market\Series;

use App\Domains\Lands\Models\Land;
use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Data\PricedListing;
use App\Domains\Market\Data\SizeRanges;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Enums\PriceUnit;
use Carbon\CarbonImmutable;

/**
 * Price per m² of lands for sale, in dollars: the total price mostly reflects the size of the plot.
 * Lands without a known area are left out.
 */
class LandsForSale implements PriceSeries
{
    public function key(): string
    {
        return 'lands_for_sale';
    }

    public function label(): string
    {
        return 'Terrenos en venta';
    }

    public function unit(): PriceUnit
    {
        return PriceUnit::PricePerSquareMeter;
    }

    public function currencies(): array
    {
        return [Currency::Usd];
    }

    public function groupsByBedrooms(): bool
    {
        return false;
    }

    /**
     * Bigger plots are cheaper per m², so places are compared by plot size.
     */
    public function sizeRanges(): ?SizeRanges
    {
        return SizeRanges::fromBounds('Superficie', config('market.size_ranges.'.$this->key()));
    }

    public function listingsPublishedIn(CarbonImmutable $month): iterable
    {
        return Land::query()
            ->with('firstKnownPrice')
            ->where('area', '>', 0)
            ->whereBetween('created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->lazy()
            ->map(function (Land $land) {
                // The price it was published with: later changes belong to later months, not to this one.
                $price = $land->firstKnownPrice ?? $land;
                if ($price->price_amount === null) {
                    return null;
                }

                return new PricedListing(
                    amount: (float) $price->price_amount / $land->area,
                    currency: Currency::from($price->price_currency),
                    city: $land->city,
                    size: $land->area,
                );
            })
            ->filter();
    }

    public function firstPublishedAt(): ?CarbonImmutable
    {
        $oldest = Land::min('created_at');

        return $oldest === null ? null : CarbonImmutable::parse($oldest);
    }
}
