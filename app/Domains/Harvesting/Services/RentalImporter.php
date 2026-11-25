<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Data\ImportResult;
use App\Domains\Harvesting\Data\ListingMatch;
use App\Domains\Harvesting\Enums\MatchMethod;
use App\Domains\Harvesting\Enums\PriceReview;
use App\Domains\Harvesting\Support\PriceChangeDetector;
use App\Domains\Rentals\Data\ParsedPrice;
use App\Domains\Rentals\Models\Rental;
use App\Domains\Rentals\Support\PriceParser;
use App\Domains\Rentals\Support\RentalLink;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\Log;
use Throwable;

class RentalImporter
{
    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly RentalClassifier $rentalClassifier,
        private readonly RentalImageChecker $imageChecker,
        private readonly ListingMatcher $listingMatcher,
        private readonly PriceChangeDetector $priceChangeDetector,
    ) {}

    /**
     * Each item is stored independently: one bad item doesn't drop the rest of the site.
     */
    public function import(array $scrapedItems, Source $source): ImportResult
    {
        $siteUrl = $source->listingUrl;
        $newItems = [];
        $failedCount = 0;

        foreach ($scrapedItems as $item) {
            try {
                $rental = $this->storeItem($item, $source);
                $this->rentalClassifier->classify($rental, $source);
                $this->refreshRentedStatus($rental);

                $isNewAndAvailable = $rental->wasRecentlyCreated && ! $rental->isRented();
                if ($isNewAndAvailable) {
                    $newItems[] = [...$item, 'site_url' => $siteUrl];
                }
            } catch (Throwable $e) {
                $failedCount++;
                $link = is_array($item) ? ($item['Link'] ?? 'unknown') : 'invalid item';
                Log::error("❌ Error storing rental {$link} from {$siteUrl}: ".$e->getMessage());
            }
        }

        $this->deactivateMissingRentals($scrapedItems, $siteUrl);
        $pendingPriceCount = Rental::where('site_path', $siteUrl)->where('active', true)->whereNotNull('pending_price_amount')->count();

        Log::info('✅ Stored '.count($newItems)." new rentals from {$siteUrl} ({$failedCount} failed).");

        return new ImportResult($newItems, $failedCount, $pendingPriceCount);
    }

    private function storeItem(array $item, Source $source): Rental
    {
        $link = RentalLink::normalize($item['Link']);
        $externalId = $source->listingIdFrom($link);
        $price = $this->priceParser->parse($item['Price'] ?? null, $item['Currency'] ?? null);

        $match = $this->listingMatcher->find(Rental::class, $link, $externalId, $source->listingUrl);
        if ($match !== null) {
            return $this->updateExisting($match, $item, $link, $externalId, $price);
        }

        $rental = Rental::create([
            'source' => $link,
            'external_id' => $externalId,
            'content' => $item['Content'],
            'description' => $item['Caracteristicas'] ?? null,
            'price' => $price->raw,
            'price_amount' => $price->amount,
            'price_currency' => $price->currency,
            'location' => $item['Location'] ?? null,
            'rooms' => $item['Rooms'] ?? null,
            'bathrooms' => $item['Bathrooms'] ?? null,
            'site_path' => $source->listingUrl,
            'image_url' => $item['Image'] ?? null,
            'active' => true,
        ]);
        $this->recordPrice($rental, MatchMethod::Created);

        return $rental;
    }

    /**
     * The rental always keeps its latest confirmed price and URL; each confirmed price change (or a URL change) adds a row to its history.
     */
    private function updateExisting(ListingMatch $match, array $item, string $link, ?string $externalId, ParsedPrice $price): Rental
    {
        /** @var Rental $rental */
        $rental = $match->listing;
        $hasNewLink = $rental->source !== $link;
        $priceReview = $this->priceChangeDetector->review($rental, $price);

        $rental->update([
            'active' => true,
            'deactivated_at' => null,
            'source' => $link,
            'external_id' => $externalId ?? $rental->external_id,
            'image_url' => $item['Image'] ?? $rental->image_url,
            ...$priceReview->attributes($price),
        ]);

        if ($hasNewLink) {
            Log::info("🔗 Rental recognized by {$match->method->value} with a new URL: {$link}");
        }

        if ($priceReview === PriceReview::Pending) {
            Log::info("⏳ New price {$price->amount} {$price->currency} to confirm on the next read: {$link}");
        }

        if ($priceReview === PriceReview::Confirmed || $hasNewLink) {
            $this->recordPrice($rental, $match->method);
        }

        return $rental;
    }

    private function recordPrice(Rental $rental, MatchMethod $method): void
    {
        $rental->priceChanges()->create([
            'price' => $rental->price,
            'price_amount' => $rental->price_amount,
            'price_currency' => $rental->price_currency,
            'match_method' => $method->value,
            'match_confidence' => $method->confidence(),
            'observed_at' => now(),
        ]);
    }

    /**
     * Checks the photo for an "ALQUILADO" stamp, only once per image URL.
     * If the check fails, the rental stays as it was and is retried next time.
     */
    private function refreshRentedStatus(Rental $rental): void
    {
        $hasUncheckedImage = $rental->image_url !== null && $rental->image_url !== $rental->image_checked_url;
        if (! $hasUncheckedImage) {
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
            ->filter(fn ($item) => is_array($item) && ! empty($item['Link']))
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
                // A pending price can't be confirmed anymore: without this the source would be re-read forever.
                ->update(['active' => false, 'deactivated_at' => now(), 'pending_price' => null, 'pending_price_amount' => null, 'pending_price_currency' => null]);

            Log::info("🔄 {$deactivatedCount} rentals deactivated for site: {$siteUrl}");
        } catch (Throwable $e) {
            Log::error("❌ Error deactivating rentals for {$siteUrl}: ".$e->getMessage());
        }
    }
}
