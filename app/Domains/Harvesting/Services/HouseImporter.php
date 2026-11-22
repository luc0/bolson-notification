<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Data\ImportResult;
use App\Domains\Houses\Models\House;
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
                $house = $this->storeItem($item, $siteUrl);
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

        Log::info('✅ Stored '.count($newItems)." new houses from {$siteUrl} ({$failedCount} failed).");

        return new ImportResult($newItems, $failedCount);
    }

    private function storeItem(array $item, string $siteUrl): House
    {
        $link = RentalLink::normalize($item['Link']);
        $imageUrl = $item['Image'] ?? null;
        $existingHouse = House::where('source', $link)->first();

        if ($existingHouse) {
            $existingHouse->update([
                'active' => true,
                'image_url' => $imageUrl ?? $existingHouse->image_url,
            ]);

            return $existingHouse;
        }

        $price = $this->priceParser->parse($item['Price'] ?? null, $item['Currency'] ?? null);
        $isRealPrice = $price->amount >= self::MIN_REAL_SALE_PRICE;

        return House::create([
            'source' => $link,
            'content' => $item['Content'],
            'description' => $item['Caracteristicas'] ?? null,
            'price' => $price->raw,
            'price_amount' => $isRealPrice ? $price->amount : null,
            'price_currency' => $isRealPrice ? $price->currency : null,
            'location' => $item['Location'] ?? null,
            'bedrooms' => $this->positiveOrNull($item['Bedrooms'] ?? null),
            'bathrooms' => $this->positiveOrNull($item['Bathrooms'] ?? null),
            'covered_area' => $this->positiveOrNull($item['CoveredArea'] ?? null),
            'total_area' => $this->positiveOrNull($item['TotalArea'] ?? null),
            'image_url' => $imageUrl,
            'site_path' => $siteUrl,
            'active' => true,
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
                ->update(['active' => false]);

            Log::info("🔄 {$deactivatedCount} houses deactivated for site: {$siteUrl}");
        } catch (Throwable $e) {
            Log::error("❌ Error deactivating houses for {$siteUrl}: ".$e->getMessage());
        }
    }
}
