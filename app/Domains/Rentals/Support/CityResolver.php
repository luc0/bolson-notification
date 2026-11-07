<?php

namespace App\Domains\Rentals\Support;

use App\Domains\Rentals\Data\ResolvedLocation;
use App\Domains\Rentals\Enums\City;
use Illuminate\Support\Str;

/**
 * Finds the city and neighborhood of a rental in its texts (location, title...) by matching
 * city aliases and known neighborhoods. Deterministic and evidence-based: never guesses.
 */
class CityResolver
{
    /**
     * @param  array<int, string|null>  $texts  most reliable first (e.g. location, then title).
     */
    public function resolve(array $texts): ResolvedLocation
    {
        $normalizedTexts = array_values(array_filter(array_map(fn (?string $text) => $this->normalize($text), $texts)));

        $explicitCity = $this->findCity($normalizedTexts);
        $neighborhood = $this->findNeighborhood($normalizedTexts);

        // e.g. "Av. Arrayanes, Lago Puelo" is a street there, not the Arrayanes neighborhood of El Bolsón.
        $isNeighborhoodOfAnotherCity = $explicitCity !== null && $neighborhood?->city !== $explicitCity;
        if ($isNeighborhoodOfAnotherCity) {
            return new ResolvedLocation($explicitCity);
        }

        return new ResolvedLocation($explicitCity ?? $neighborhood?->city, $neighborhood?->neighborhood);
    }

    /**
     * @param  string[]  $normalizedTexts
     */
    private function findCity(array $normalizedTexts): ?City
    {
        foreach ($normalizedTexts as $text) {
            $city = $this->firstCityIn($text);
            if ($city !== null) {
                return $city;
            }
        }

        return null;
    }

    /**
     * When a text names more than one city, the one written first wins.
     */
    private function firstCityIn(string $text): ?City
    {
        $firstCity = null;
        $firstPosition = PHP_INT_MAX;

        foreach (City::cases() as $city) {
            foreach ($city->aliases() as $alias) {
                $position = $this->wordPosition($text, $alias);
                $isEarlier = $position !== null && $position < $firstPosition;
                if ($isEarlier) {
                    $firstCity = $city;
                    $firstPosition = $position;
                }
            }
        }

        return $firstCity;
    }

    /**
     * @param  string[]  $normalizedTexts
     * @return ResolvedLocation|null the neighborhood and the city it belongs to.
     */
    private function findNeighborhood(array $normalizedTexts): ?ResolvedLocation
    {
        foreach ($normalizedTexts as $text) {
            foreach (City::cases() as $city) {
                foreach ($city->neighborhoods() as $neighborhood) {
                    $isMentioned = $this->wordPosition($text, $this->normalize($neighborhood)) !== null;
                    if ($isMentioned) {
                        return new ResolvedLocation($city, $neighborhood);
                    }
                }
            }
        }

        return null;
    }

    private function wordPosition(string $text, string $words): ?int
    {
        $found = preg_match('/\b'.preg_quote($words, '/').'\b/', $text, $matches, PREG_OFFSET_CAPTURE) === 1;
        if (! $found) {
            return null;
        }

        return $matches[0][1];
    }

    private function normalize(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return Str::of($text)->ascii()->lower()->squish()->toString();
    }
}
