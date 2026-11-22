<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Support\CaptureStore;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\Log;

/**
 * Captures every house-for-sale source with Puppeteer, then extracts and imports the houses of the sources
 * whose capture changed since the last successful run. Captures live apart from the rental and land ones.
 */
class BrowserHouseScraper
{
    private readonly CaptureStore $captureStore;

    public function __construct(
        private readonly SourceCapturer $sourceCapturer,
        private readonly HouseListingExtractor $houseListingExtractor,
        private readonly HouseImporter $houseImporter,
    ) {
        $this->captureStore = CaptureStore::forHouses();
    }

    /**
     * @return array new houses.
     */
    public function scrape(): array
    {
        $sources = Source::houses();
        if (empty($sources)) {
            return [];
        }

        $captured = $this->sourceCapturer->capture($sources, $this->captureStore->capturePath());
        if (! $captured) {
            return [];
        }

        return collect($sources)
            ->flatMap(fn (Source $source) => $this->processSource($source))
            ->all();
    }

    /**
     * @return array new houses of this source.
     */
    private function processSource(Source $source): array
    {
        $capture = $this->captureStore->find($source);
        if ($capture === null) {
            Log::error("❌ House capture not found for {$source->key}.");

            return [];
        }

        if ($this->captureStore->isUnchanged($source, $capture)) {
            Log::info("🔁 No house changes for {$source->key}.");

            return [];
        }

        Log::info("📤 Sending {$source->key} houses to AI for analysis.");
        $extractedHouses = $this->houseListingExtractor->extract($source, $capture->html);

        $aiFailed = $extractedHouses === null;
        if ($aiFailed) {
            Log::warning("⚠️ AI failed for {$source->key} houses, snapshot not saved so it's retried next run.");

            return [];
        }

        $importResult = $this->houseImporter->import($capture->withImageUrls($extractedHouses), $source);

        if ($importResult->hasFailures()) {
            Log::warning("⚠️ Some houses failed for {$source->key}, snapshot not saved so it's retried next run.");

            return $importResult->newItems;
        }

        $this->captureStore->markProcessed($source, $capture);

        return $importResult->newItems;
    }
}
