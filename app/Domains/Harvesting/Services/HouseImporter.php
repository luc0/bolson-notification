<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Data\ImportResult;
use App\Domains\Harvesting\Data\ListingMatch;
use App\Domains\Harvesting\Enums\MatchMethod;
use App\Domains\Harvesting\Enums\PriceReview;
use App\Domains\Harvesting\Support\PriceChangeDetector;
use App\Domains\Houses\Models\House;
use App\Domains\Rentals\Data\ParsedPrice;
use App\Domains\Rentals\Support\PriceParser;
use App\Domains\Rentals\Support\RentalLink;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imports the houses extracted from a source into Houses: create, reactivate, classify and deactivate.
 */
class HouseImporter
{
    // A sale price below this is a placeholder (agencies publish "U\$S 200" for "ask for the price").
    private const MIN_REAL_SALE_PRICE = 1000;

    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly HouseClassifier $houseClassifier,
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
                $house = $this->storeItem($item, $source);
                $this->houseClassifier->classify($house, $source);
                if ($house->wasRecentlyCreated) {
                    $newItems[] = [...$item, 'site_url' => $siteUrl];
                }
            } catch (Throwable $e) {
                $failedCount++;
                $link = is_array($item) ? ($item['Link'] ?? 'unknown') : 'invalid item';
                Log::error("❌ Error storing house {$link} from {$siteUrl}: ".$e->getMessage());
            }
        }

        $this->deactivateMissingHouses($scrapedItems, $siteUrl);
        $pendingPriceCount = House::where('site_path', $siteUrl)->where('active', true)->whereNotNull('pending_price_amount')->count();

        Log::info('✅ Stored '.count($newItems)." new houses from {$siteUrl} ({$failedCount} failed).");

        return new ImportResult($newItems, $failedCount, $pendingPriceCount);
    }

    private function storeItem(array $item, Source $source): House
    {
        $link = RentalLink::normalize($item['Link']);
        $externalId = $source->listingIdFrom($link);
        $price = $this->salePrice($item);

        $match = $this->listingMatcher->find(House::class, $link, $externalId, $source->listingUrl);
        if ($match !== null) {
            return $this->updateExisting($match, $item, $link, $externalId, $price);
        }

        $house = House::create([
            'source' => $link,
            'external_id' => $externalId,
            'content' => $item['Content'],
            'description' => $item['Caracteristicas'] ?? null,
            'price' => $price->raw,
            'price_amount' => $price->amount,
            'price_currency' => $price->currency,
            'location' => $item['Location'] ?? null,
            'bedrooms' => $this->positiveOrNull($item['Bedrooms'] ?? null),
            'bathrooms' => $this->positiveOrNull($item['Bathrooms'] ?? null),
            'covered_area' => $this->positiveOrNull($item['CoveredArea'] ?? null),
            'total_area' => $this->positiveOrNull($item['TotalArea'] ?? null),
            'image_url' => $item['Image'] ?? null,
            'site_path' => $source->listingUrl,
            'active' => true,
        ]);
        $this->recordPrice($house, MatchMethod::Created);

        return $house;
    }

    /**
     * A placeholder price ("U\$S 200" for "ask for the price") is stored as unknown.
     */
    private function salePrice(array $item): ParsedPrice
    {
        $price = $this->priceParser->parse($item['Price'] ?? null, $item['Currency'] ?? null);
        $isRealPrice = $price->amount >= self::MIN_REAL_SALE_PRICE;
        if (! $isRealPrice) {
            return new ParsedPrice($price->raw, null, null);
        }

        return $price;
    }

    /**
     * The house always keeps its latest confirmed price and URL; each confirmed price change (or a URL change) adds a row to its history.
     */
    private function updateExisting(ListingMatch $match, array $item, string $link, ?string $externalId, ParsedPrice $price): House
    {
        /** @var House $house */
        $house = $match->listing;
        $hasNewLink = $house->source !== $link;
        $priceReview = $this->priceChangeDetector->review($house, $price);

        $house->update([
            'active' => true,
            'deactivated_at' => null,
            'source' => $link,
            'external_id' => $externalId ?? $house->external_id,
            'image_url' => $item['Image'] ?? $house->image_url,
            ...$priceReview->attributes($price),
        ]);

        if ($hasNewLink) {
            Log::info("🔗 House recognized by {$match->method->value} with a new URL: {$link}");
        }

        if ($priceReview === PriceReview::Pending) {
            Log::info("⏳ New price {$price->amount} {$price->currency} to confirm on the next read: {$link}");
        }

        if ($priceReview === PriceReview::Confirmed || $hasNewLink) {
            $this->recordPrice($house, $match->method);
        }

        return $house;
    }

    private function recordPrice(House $house, MatchMethod $method): void
    {
        $house->priceChanges()->create([
            'price' => $house->price,
            'price_amount' => $house->price_amount,
            'price_currency' => $house->price_currency,
            'match_method' => $method->value,
            'match_confidence' => $method->confidence(),
            'observed_at' => now(),
        ]);
    }

    /**
     * Agencies publish 0 when they don't have the data.
     */
    private function positiveOrNull(int|float|null $value): int|float|null
    {
        return $value > 0 ? $value : null;
    }

    private function deactivateMissingHouses(array $scrapedItems, string $siteUrl): void
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
            $deactivatedCount = House::where('site_path', $siteUrl)
                ->where('active', true)
                ->whereNotIn('source', $scrapedSources)
                // A pending price can't be confirmed anymore: without this the source would be re-read forever.
                ->update(['active' => false, 'deactivated_at' => now(), 'pending_price' => null, 'pending_price_amount' => null, 'pending_price_currency' => null]);

            Log::info("🔄 {$deactivatedCount} houses deactivated for site: {$siteUrl}");
        } catch (Throwable $e) {
            Log::error("❌ Error deactivating houses for {$siteUrl}: ".$e->getMessage());
        }
    }
}
