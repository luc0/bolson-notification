<?php

namespace App\Domains\Market\Services;

use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Models\PriceStat;
use App\Domains\Market\Support\PriceSeriesRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Stores the price stats of every series for a month, replacing the ones already stored for it.
 */
class MarketSnapshot
{
    public function __construct(
        private readonly PriceSeriesRegistry $registry,
        private readonly ExchangeRates $exchangeRates,
        private readonly PriceStatsCalculator $calculator,
    ) {}

    /**
     * @return int price_stats rows stored.
     */
    public function store(CarbonImmutable $month): int
    {
        $series = $this->registry->all();
        $arsPerUsd = $this->exchangeRates->arsPerUsd($month);
        $rows = collect($series)
            ->flatMap(fn (PriceSeries $series) => $this->calculator->calculate($series, $month, $arsPerUsd))
            ->map(fn (array $row) => [...$row, 'created_at' => now(), 'updated_at' => now()]);

        DB::transaction(function () use ($series, $month, $rows) {
            PriceStat::whereDate('period', $month->startOfMonth())
                ->whereIn('series', collect($series)->map(fn (PriceSeries $series) => $series->key()))
                ->delete();
            PriceStat::insert($rows->all());
        });

        return $rows->count();
    }

    /**
     * The month the oldest listing of any series was first seen.
     */
    public function firstMonth(): ?CarbonImmutable
    {
        return collect($this->registry->all())
            ->map(fn (PriceSeries $series) => $series->firstPublishedAt())
            ->filter()
            ->min()
            ?->startOfMonth();
    }
}
