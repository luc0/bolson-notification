<?php

namespace App\Domains\Market\Queries;

use App\Domains\Locations\Enums\City;
use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Models\PriceStat;
use Illuminate\Support\Collection;

/**
 * Month by month stats of a series for one chart: one line per place (the whole Comarca and every city
 * with data), every bedroom count, in the chosen currency when the series has it (otherwise in its own).
 */
final readonly class PriceHistory
{
    /**
     * Place key of the stats of every city (price_stats.city null).
     */
    public const COMARCA = 'comarca';

    public function __construct(
        public Currency $currency = Currency::Ars,
    ) {}

    /**
     * Places a chart can show, Comarca first: key => label.
     *
     * @return array<string, string>
     */
    public static function places(): array
    {
        $cities = array_map(fn (City $city) => $city->label(), City::cases());

        return [self::COMARCA => 'Toda la Comarca', ...array_combine(City::values(), $cities)];
    }

    /**
     * Lines share the months list: a month a place has no stats for is null.
     *
     * @return array{key: string, label: string, unit: string, currency: string, symbol: string, months: string[],
     *     lines: array<int, array{place: string, label: string,
     *         points: array<int, array{median: float, p25: float, p75: float, average: float, count: int}|null>}>}
     */
    public function chart(PriceSeries $series): array
    {
        $currency = $this->currencyFor($series);

        $stats = PriceStat::query()
            ->where('series', $series->key())
            ->where('currency', $currency)
            ->whereNull('bedrooms')
            ->orderBy('period')
            ->get();

        $statPerPeriod = $stats->unique(fn (PriceStat $stat) => $stat->period->toDateString())->values();
        $periods = $statPerPeriod->map(fn (PriceStat $stat) => $stat->period->toDateString());
        $statsByPlace = $stats->groupBy(fn (PriceStat $stat) => $stat->city->value ?? self::COMARCA);

        $lines = collect(self::places())
            ->filter(fn (string $label, string $place) => $statsByPlace->has($place))
            ->map(fn (string $label, string $place) => [
                'place' => $place,
                'label' => $label,
                'points' => $this->pointsByPeriod($statsByPlace[$place], $periods),
            ])
            ->values()
            ->all();

        return [
            'key' => $series->key(),
            'label' => $series->label(),
            'unit' => $series->unit()->label(),
            'currency' => $currency->value,
            'symbol' => $currency->symbol(),
            'months' => $statPerPeriod->map($this->monthLabel(...))->all(),
            'lines' => $lines,
        ];
    }

    public function currencyFor(PriceSeries $series): Currency
    {
        $hasChosenCurrency = in_array($this->currency, $series->currencies(), true);

        return $hasChosenCurrency ? $this->currency : $series->currencies()[0];
    }

    /**
     * @param  Collection<int, PriceStat>  $stats
     * @param  Collection<int, string>  $periods
     * @return array<int, array{median: float, p25: float, p75: float, average: float, count: int}|null>
     */
    private function pointsByPeriod(Collection $stats, Collection $periods): array
    {
        $statsByPeriod = $stats->keyBy(fn (PriceStat $stat) => $stat->period->toDateString());

        return $periods->map(function (string $period) use ($statsByPeriod) {
            $stat = $statsByPeriod->get($period);

            return $stat ? [
                'median' => $stat->median,
                'p25' => $stat->p25,
                'p75' => $stat->p75,
                'average' => $stat->average,
                'count' => $stat->listings_count,
            ] : null;
        })->all();
    }

    /**
     * "Oct 2026".
     */
    private function monthLabel(PriceStat $stat): string
    {
        return ucfirst(str_replace('.', '', $stat->period->locale('es')->translatedFormat('M Y')));
    }
}
