<?php

namespace App\Domains\Harvesting\Lands\Services;

use App\Domains\Harvesting\Data\ImportResult;
use App\Domains\Lands\Models\Land;
use App\Domains\Rentals\Support\PriceParser;
use App\Domains\Rentals\Support\RentalLink;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imports the lands extracted from a source into Lands: create, reactivate and deactivate.
 */
class LandImporter
{
    // A sale price below this is a placeholder (agencies publish "U\$S 200" for "ask for the price").
    private const MIN_REAL_SALE_PRICE = 1000;

    public function __construct(private readonly PriceParser $priceParser) {}

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
                $land = $this->storeItem($item, $siteUrl);
                if ($land->wasRecentlyCreated) {
                    $newItems[] = [...$item, 'site_url' => $siteUrl];
                }
            } catch (Throwable $e) {
                $failedCount++;
                $link = is_array($item) ? ($item['Link'] ?? 'unknown') : 'invalid item';
                Log::error("❌ Error storing land {$link} from {$siteUrl}: ".$e->getMessage());
            }
        }

        $this->deactivateMissingLands($scrapedItems, $siteUrl);

        Log::info('✅ Stored '.count($newItems)." new lands from {$siteUrl} ({$failedCount} failed).");

        return new ImportResult($newItems, $failedCount);
    }

    private function storeItem(array $item, string $siteUrl): Land
    {
        $link = RentalLink::normalize($item['Link']);
        $imageUrl = $item['Image'] ?? null;
        $existingLand = Land::where('source', $link)->first();

        if ($existingLand) {
            $existingLand->update([
                'active' => true,
                'image_url' => $imageUrl ?? $existingLand->image_url,
            ]);

            return $existingLand;
        }

        $price = $this->priceParser->parse($item['Price'] ?? null, $item['Currency'] ?? null);
        $isRealPrice = $price->amount >= self::MIN_REAL_SALE_PRICE;
        $area = $item['Area'] ?? null;

        return Land::create([
            'source' => $link,
            'content' => $item['Content'],
            'description' => $item['Caracteristicas'] ?? null,
            'price' => $price->raw,
            'price_amount' => $isRealPrice ? $price->amount : null,
            'price_currency' => $isRealPrice ? $price->currency : null,
            'location' => $item['Location'] ?? null,
            'area' => $area > 0 ? $area : null,
            'image_url' => $imageUrl,
            'site_path' => $siteUrl,
            'active' => true,
        ]);
    }

    private function deactivateMissingLands(array $scrapedItems, string $siteUrl): void
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
            $deactivatedCount = Land::where('site_path', $siteUrl)
                ->where('active', true)
                ->whereNotIn('source', $scrapedSources)
                ->update(['active' => false]);

            Log::info("🔄 {$deactivatedCount} lands deactivated for site: {$siteUrl}");
        } catch (Throwable $e) {
            Log::error("❌ Error deactivating lands for {$siteUrl}: ".$e->getMessage());
        }
    }
}
