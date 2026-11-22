<?php

namespace App\Domains\Market\Contracts;

use App\Domains\Market\Data\PricedListing;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Enums\PriceUnit;
use Carbon\CarbonImmutable;

/**
 * A price series of the Análisis section (e.g. residential rentals). To add one, implement this
 * and list it in config/market.php: MarketSnapshot and the charts pick it up.
 */
interface PriceSeries
{
    /**
     * Stored in price_stats.series and used in URLs.
     */
    public function key(): string;

    /**
     * Chart title.
     */
    public function label(): string;

    public function unit(): PriceUnit;

    /**
     * Currencies its stats are stored in; listings in another currency are converted.
     *
     * @return Currency[]
     */
    public function currencies(): array;

    /**
     * Also store stats per bedroom count, besides the stats of every bedroom count.
     */
    public function groupsByBedrooms(): bool;

    /**
     * Listings first seen in that month (available or not), with a known price.
     *
     * @return iterable<PricedListing>
     */
    public function listingsPublishedIn(CarbonImmutable $month): iterable;

    /**
     * When the oldest listing was first seen, to know where history starts.
     */
    public function firstPublishedAt(): ?CarbonImmutable;
}
