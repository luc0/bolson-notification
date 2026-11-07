<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Support\CaptureStore;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\Log;

/**
 * Captures every land source with Puppeteer, then extracts and imports the lands of the sources
 * whose capture changed since the last successful run. Captures live apart from the rental ones.
 */
class BrowserLandScraper
{
    private readonly CaptureStore $captureStore;

    public function __construct(
        private readonly SourceCapturer $sourceCapturer,
        private readonly LandListingExtractor $landListingExtractor,
        private readonly LandImporter $landImporter,
    ) {
        $this->captureStore = CaptureStore::forLands();
    }

    /**
     * @return array new lands.
     */
    public function scrape(): array
    {
        $sources = Source::lands();
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
     * @return array new lands of this source.
     */
    private function processSource(Source $source): array
    {
        $capture = $this->captureStore->find($source);
        if ($capture === null) {
            Log::error("❌ Land capture not found for {$source->key}.");

            return [];
        }

        if ($this->captureStore->isUnchanged($source, $capture)) {
            Log::info("🔁 No land changes for {$source->key}.");

            return [];
        }

        Log::info("📤 Sending {$source->key} lands to AI for analysis.");
        $extractedLands = $this->landListingExtractor->extract($source, $capture->html);

        $aiFailed = $extractedLands === null;
        if ($aiFailed) {
            Log::warning("⚠️ AI failed for {$source->key} lands, snapshot not saved so it's retried next run.");

            return [];
        }

        $importResult = $this->landImporter->import($capture->withImageUrls($extractedLands), $source);

        if ($importResult->hasFailures()) {
            Log::warning("⚠️ Some lands failed for {$source->key}, snapshot not saved so it's retried next run.");

            return $importResult->newItems;
        }

        $this->captureStore->markProcessed($source, $capture);

        return $importResult->newItems;
    }
}
