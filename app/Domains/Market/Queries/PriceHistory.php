<?php

namespace App\Domains\Market\Queries;

use App\Domains\Locations\Enums\City;
use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Models\PriceStat;

/**
 * Month by month stats of a series for one chart: a city (null = whole Comarca), every bedroom count,
 * in the chosen currency when the series has it (otherwise in its own currency).
 */
final readonly class PriceHistory
{
    public function __construct(
        public ?City $city = null,
        public Currency $currency = Currency::Ars,
    ) {}

    /**
     * @return array{key: string, label: string, unit: string, currency: string, symbol: string,
     *     points: array<int, array{month: string, median: float, p25: float, p75: float, average: float, count: int}>}
     */
    public function chart(PriceSeries $series): array
    {
        $currency = $this->currencyFor($series);

        $points = PriceStat::query()
            ->where('series', $series->key())
            ->where('currency', $currency)
            ->whereNull('bedrooms')
            ->when($this->city, fn ($query) => $query->where('city', $this->city), fn ($query) => $query->whereNull('city'))
            ->orderBy('period')
            ->get()
            ->map(fn (PriceStat $stat) => [
                'month' => $this->monthLabel($stat),
                'median' => $stat->median,
                'p25' => $stat->p25,
                'p75' => $stat->p75,
                'average' => $stat->average,
                'count' => $stat->listings_count,
            ])
            ->all();

        return [
            'key' => $series->key(),
            'label' => $series->label(),
            'unit' => $series->unit()->label(),
            'currency' => $currency->value,
            'symbol' => $currency->symbol(),
            'points' => $points,
        ];
    }

    public function currencyFor(PriceSeries $series): Currency
    {
        $hasChosenCurrency = in_array($this->currency, $series->currencies(), true);

        return $hasChosenCurrency ? $this->currency : $series->currencies()[0];
    }

    /**
     * "Oct 2026".
     */
    private function monthLabel(PriceStat $stat): string
    {
        return ucfirst(str_replace('.', '', $stat->period->locale('es')->translatedFormat('M Y')));
    }
}
