<?php

namespace App\Domains\Market\Services;

use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Data\PricedListing;
use App\Domains\Market\Data\SizeRange;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Support\Distribution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns the listings of a series published in a month into price_stats rows: one per currency of the
 * series and group (whole Comarca + each city, and each bedroom count / size range when the series has them).
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
                    'size_range' => $group['size_range'],
                    'currency' => $currency->value,
                    'unit' => $series->unit()->value,
                    ...$this->stats($group['listings']),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Every city (and the whole Comarca) broken down into: every listing, each bedroom count and each size range
     * (each breakdown on its own, not combined).
     *
     * @param  Collection<int, PricedListing>  $listings
     * @return Collection<int, array{city: ?string, bedrooms: ?int, size_range: ?string, listings: Collection<int, PricedListing>}>
     */
    private function groups(PriceSeries $series, Collection $listings): Collection
    {
        $byCity = $listings
            ->filter(fn (PricedListing $listing) => $listing->city !== null)
            ->groupBy(fn (PricedListing $listing) => $listing->city->value);
        $cityGroups = collect([null => $listings])->union($byCity);

        return $cityGroups
            ->flatMap(fn (Collection $cityListings, string $city) => collect([
                ['bedrooms' => null, 'size_range' => null, 'listings' => $cityListings],
                ...$this->bedroomGroups($series, $cityListings),
                ...$this->sizeGroups($series, $cityListings),
            ])->map(fn (array $group) => ['city' => $city === '' ? null : $city, ...$group]))
            ->filter(fn (array $group) => $group['listings']->isNotEmpty())
            ->values();
    }

    /**
     * @param  Collection<int, PricedListing>  $listings
     * @return array<int, array{bedrooms: int, size_range: null, listings: Collection<int, PricedListing>}>
     */
    private function bedroomGroups(PriceSeries $series, Collection $listings): array
    {
        if (! $series->groupsByBedrooms()) {
            return [];
        }

        return $listings
            ->filter(fn (PricedListing $listing) => $listing->bedrooms !== null)
            ->groupBy(fn (PricedListing $listing) => min($listing->bedrooms, self::MANY_BEDROOMS))
            ->sortKeys()
            ->map(fn (Collection $group, int $bedrooms) => ['bedrooms' => $bedrooms, 'size_range' => null, 'listings' => $group])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PricedListing>  $listings
     * @return array<int, array{bedrooms: null, size_range: string, listings: Collection<int, PricedListing>}>
     */
    private function sizeGroups(PriceSeries $series, Collection $listings): array
    {
        $sizeRanges = $series->sizeRanges();
        if ($sizeRanges === null) {
            return [];
        }

        return array_map(fn (SizeRange $range) => [
            'bedrooms' => null,
            'size_range' => $range->key(),
            'listings' => $listings->filter(fn (PricedListing $listing) => $sizeRanges->rangeOf($listing->size)?->key() === $range->key())->values(),
        ], $sizeRanges->ranges);
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

        return new PricedListing($amount, $currency, $listing->city, $listing->bedrooms, $listing->size);
    }
}
