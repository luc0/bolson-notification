<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Enums\Utility;
use App\Domains\Rentals\Models\Rental;
use Illuminate\Support\Facades\Log;

/**
 * Completes available rentals with the data of their detail page (area, bedrooms, services...).
 * Each rental is processed once; failures are retried up to MAX_ATTEMPTS times on later runs.
 */
class RentalDetailService
{
    public const MAX_ATTEMPTS = 3;

    // Be gentle with the agencies' sites.
    private const DELAY_BETWEEN_REQUESTS_MS = 500;

    public function __construct(
        private readonly RentalDetailFetcher $fetcher,
        private readonly RentalDetailExtractor $extractor,
    ) {}

    /**
     * @return int how many rentals were completed.
     */
    public function enrichPending(int $limit = 200, bool $missingTitlesOnly = false): int
    {
        $pendingRentals = Rental::available()
            ->when(
                $missingTitlesOnly,
                // Backfill: rentals that already have their detail but no AI title (one AI call each).
                fn ($query) => $query->whereNull('title'),
                fn ($query) => $query->whereNull('details_fetched_at')->where('details_attempts', '<', self::MAX_ATTEMPTS),
            )
            ->limit($limit)
            ->get();

        $enrichedCount = $pendingRentals->filter(function (Rental $rental) {
            $wasEnriched = $this->enrich($rental);
            usleep(self::DELAY_BETWEEN_REQUESTS_MS * 1000);

            return $wasEnriched;
        })->count();

        Log::info("🔎 Details completed for {$enrichedCount}/{$pendingRentals->count()} rentals.");

        return $enrichedCount;
    }

    public function enrich(Rental $rental): bool
    {
        $rental->increment('details_attempts');

        $pageText = $this->fetcher->fetchText($rental->source);
        if ($pageText === null) {
            return false;
        }

        // The agency's title helps the AI title (bedrooms, neighborhood) when the page text lacks it.
        $detail = $this->extractor->extract("Título del aviso: {$rental->content}\n\n{$pageText}", $rental->source);
        if ($detail === null) {
            return false;
        }

        $rental->update([
            'title' => $detail['Title'] ?? $rental->title,
            'covered_area' => $this->positiveOrNull($detail['CoveredArea']),
            'total_area' => $this->positiveOrNull($detail['TotalArea']),
            'bedrooms' => $detail['Bedrooms'],
            'parking_spaces' => $detail['ParkingSpaces'],
            'age_years' => $detail['AgeYears'],
            'services' => array_values(array_unique(array_intersect($detail['Services'], Utility::values()))),
            // The detail page is more reliable than the listing card: keep the card value only if the page has none.
            'rooms' => $this->positiveOrNull($detail['Rooms']) ?? $rental->rooms,
            'bathrooms' => $this->positiveOrNull($detail['Bathrooms']) ?? $rental->bathrooms,
            ...$this->locationFromDetail($rental, $detail),
            // Fallback only: the title (RentalClassifier) is more reliable.
            'property_use' => $rental->property_use ?? PropertyUse::tryFrom((string) ($detail['PropertyUse'] ?? '')),
            'details_fetched_at' => now(),
        ]);

        return true;
    }

    /**
     * Fallback only: the city resolved from the listing texts (RentalImporter) is more reliable.
     */
    private function locationFromDetail(Rental $rental, array $detail): array
    {
        $detailCity = City::tryFrom((string) ($detail['City'] ?? ''));
        $city = $rental->city ?? $detailCity;

        // A neighborhood the AI found next to another city doesn't belong to this rental.
        $isNeighborhoodOfThisCity = $detailCity === null || $detailCity === $city;
        $neighborhood = $isNeighborhoodOfThisCity ? $detail['Neighborhood'] ?? null : null;

        return ['city' => $city, 'neighborhood' => $rental->neighborhood ?? $neighborhood];
    }

    private function positiveOrNull(int|float|null $value): int|float|null
    {
        $isPositive = $value !== null && $value > 0;
        if (! $isPositive) {
            return null;
        }

        return $value;
    }
}
