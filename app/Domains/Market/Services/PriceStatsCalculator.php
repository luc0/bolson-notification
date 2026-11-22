<?php

namespace App\Domains\Market\Services;

use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Data\PricedListing;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Support\Distribution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns the listings of a series published in a month into price_stats rows: one per currency of the
 * series and group (whole Comarca + each city, and each bedroom count when the series groups by them).
 * Each listing is converted to the stats currency first, so the median isn't a converted median.
 */
class PriceStatsCalculator
{
    // Bedroom counts from this one up are grouped together ("4+").
    public const MANY_BEDROOMS = 4;

    /**
     * @param  float|null  $arsPerUsd  null: listings in another currency than the stats one are left out.
     * @return array<int, array<string, mixed>> price_stats attributes.
     */
    public function calculate(PriceSeries $series, CarbonImmutable $month, ?float $arsPerUsd): array
    {
        $listings = collect($series->listingsPublishedIn($month));

        return collect($series->currencies())
            ->flatMap(function (Currency $currency) use ($series, $month, $listings, $arsPerUsd) {
                $converted = $listings
                    ->map(fn (PricedListing $listing) => $this->convert($listing, $currency, $arsPerUsd))
                    ->filter();

                return $this->groups($series, $converted)->map(fn (array $group) => [
                    'period' => $month->startOfMonth()->toDateString(),
                    'series' => $series->key(),
                    'city' => $group['city'],
                    'bedrooms' => $group['bedrooms'],
                    'currency' => $currency->value,
                    'unit' => $series->unit()->value,
                    ...$this->stats($group['listings']),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PricedListing>  $listings
     * @return Collection<int, array{city: ?string, bedrooms: ?int, listings: Collection<int, PricedListing>}>
     */
    private function groups(PriceSeries $series, Collection $listings): Collection
    {
        $byCity = $listings
            ->filter(fn (PricedListing $listing) => $listing->city !== null)
            ->groupBy(fn (PricedListing $listing) => $listing->city->value);
        $cityGroups = collect([null => $listings])->union($byCity);

        return $cityGroups
            ->flatMap(fn (Collection $cityListings, string $city) => $this->bedroomGroups($series, $cityListings)
                ->map(fn (Collection $groupListings, string $bedrooms) => [
                    'city' => $city === '' ? null : $city,
                    'bedrooms' => $bedrooms === '' ? null : (int) $bedrooms,
                    'listings' => $groupListings,
                ])
                ->values())
            ->filter(fn (array $group) => $group['listings']->isNotEmpty())
            ->values();
    }

    /**
     * @return Collection<string, Collection<int, PricedListing>> '' = every bedroom count.
     */
    private function bedroomGroups(PriceSeries $series, Collection $listings): Collection
    {
        $everyBedroomCount = collect(['' => $listings]);
        if (! $series->groupsByBedrooms()) {
            return $everyBedroomCount;
        }

        $byBedrooms = $listings
            ->filter(fn (PricedListing $listing) => $listing->bedrooms !== null)
            ->groupBy(fn (PricedListing $listing) => min($listing->bedrooms, self::MANY_BEDROOMS));

        return $everyBedroomCount->union($byBedrooms);
    }

    /**
     * @param  Collection<int, PricedListing>  $listings
     * @return array{listings_count: int, median: float, average: float, p25: float, p75: float}
     */
    private function stats(Collection $listings): array
    {
        $distribution = new Distribution($listings->map(fn (PricedListing $listing) => $listing->amount)->all());

        return [
            'listings_count' => $distribution->count(),
            'median' => round($distribution->median(), 2),
            'average' => round($distribution->average(), 2),
            'p25' => round($distribution->percentile(25), 2),
            'p75' => round($distribution->percentile(75), 2),
        ];
    }

    private function convert(PricedListing $listing, Currency $currency, ?float $arsPerUsd): ?PricedListing
    {
        if ($listing->currency === $currency) {
            return $listing;
        }

        if ($arsPerUsd === null) {
            return null;
        }

        $amount = $currency === Currency::Usd ? $listing->amount / $arsPerUsd : $listing->amount * $arsPerUsd;

        return new PricedListing($amount, $currency, $listing->city, $listing->bedrooms);
    }
}
