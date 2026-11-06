<?php

namespace App\Services;

use App\Domains\Rentals\Models\Rental;
use App\Domains\Rentals\Support\PriceParser;
use App\Domains\Rentals\Support\RentalLink;
use App\Support\RentalStoreResult;
use Illuminate\Support\Facades\Log;
use Throwable;

class RentalService
{
    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly RentalImageChecker $imageChecker,
    ) {
    }

    /**
     * Each item is stored independently: one bad item doesn't drop the rest of the site.
     */
    public function store(array $scrapedItems, string $siteUrl): RentalStoreResult
    {
        $newItems = [];
        $failedCount = 0;

        foreach ($scrapedItems as $item) {
            try {
                $rental = $this->storeItem($item, $siteUrl);
                $this->refreshRentedStatus($rental);

                $isNewAndAvailable = $rental->wasRecentlyCreated && !$rental->isRented();
                if ($isNewAndAvailable) {
                    $newItems[] = [...$item, 'site_url' => $siteUrl];
                }
            } catch (Throwable $e) {
                $failedCount++;
                $link = is_array($item) ? ($item['Link'] ?? 'unknown') : 'invalid item';
                Log::error("❌ Error storing rental {$link} from {$siteUrl}: " . $e->getMessage());
            }
        }

        $this->deactivateMissingRentals($scrapedItems, $siteUrl);

        Log::info('✅ Stored ' . count($newItems) . " new rentals from {$siteUrl} ({$failedCount} failed).");

        return new RentalStoreResult($newItems, $failedCount);
    }

    private function storeItem(array $item, string $siteUrl): Rental
    {
        $link = RentalLink::normalize($item['Link']);
        $imageUrl = $item['Image'] ?? null;
        $existingRental = Rental::where('source', $link)->first();

        if ($existingRental) {
            $existingRental->update([
                'active' => true,
                'image_url' => $imageUrl ?? $existingRental->image_url,
            ]);
            return $existingRental;
        }

        $price = $this->priceParser->parse($item['Price'] ?? null, $item['Currency'] ?? null);

        return Rental::create([
            'source' => $link,
            'content' => $item['Content'],
            'description' => $item['Caracteristicas'] ?? null,
            'price' => $price->raw,
            'price_amount' => $price->amount,
            'price_currency' => $price->currency,
            'location' => $item['Location'] ?? null,
            'rooms' => $item['Rooms'] ?? null,
            'bathrooms' => $item['Bathrooms'] ?? null,
            'site_path' => $siteUrl,
            'image_url' => $imageUrl,
            'active' => true,
        ]);
    }

    /**
     * Checks the photo for an "ALQUILADO" stamp, only once per image URL.
     * If the check fails, the rental stays as it was and is retried next time.
     */
    private function refreshRentedStatus(Rental $rental): void
    {
        $hasUncheckedImage = $rental->image_url !== null && $rental->image_url !== $rental->image_checked_url;
        if (!$hasUncheckedImage) {
            return;
        }

        $isRented = $this->imageChecker->isRented($rental->image_url);
        if ($isRented === null) {
            return;
        }

        $rentedAt = $isRented ? ($rental->rented_at ?? now()) : null;
        $rental->update(['rented_at' => $rentedAt, 'image_checked_url' => $rental->image_url]);

        if ($isRented) {
            Log::info("🔑 Rental marked as rented (photo stamp): {$rental->source}");
        }
    }

    // TODO: the AI sometimes skips a few listings (e.g. Punto Patagonia returns 34-38 of 38), so they get
    //  deactivated and reactivated on the next run. Only deactivate after missing N consecutive runs
    //  (e.g. a `missing_runs` counter reset when the rental is seen again).
    private function deactivateMissingRentals(array $scrapedItems, string $siteUrl): void
    {
        $scrapedSources = collect($scrapedItems)
            ->filter(fn ($item) => is_array($item) && !empty($item['Link']))
            ->map(fn ($item) => RentalLink::normalize($item['Link']))
            ->all();

        // Guard: an empty result most likely means a scraping/AI problem, not an empty site.
        if (empty($scrapedSources)) {
            return;
        }

        try {
            $deactivatedCount = Rental::where('site_path', $siteUrl)
                ->where('active', true)
                ->whereNotIn('source', $scrapedSources)
                ->update(['active' => false]);

            Log::info("🔄 {$deactivatedCount} rentals deactivated for site: {$siteUrl}");
        } catch (Throwable $e) {
            Log::error("❌ Error deactivating rentals for {$siteUrl}: " . $e->getMessage());
        }
    }
}
