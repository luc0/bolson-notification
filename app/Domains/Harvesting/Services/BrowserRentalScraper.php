<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Contracts\RentalScraper;
use App\Domains\Harvesting\Support\CaptureStore;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\Log;

/**
 * Captures every source with Puppeteer (scripts/scrape.cjs), then extracts and imports the rentals
 * of the sources whose capture changed since the last successful run.
 */
class BrowserRentalScraper implements RentalScraper
{
    public function __construct(
        private readonly SourceCapturer $sourceCapturer,
        private readonly CaptureStore $captureStore,
        private readonly ListingExtractor $listingExtractor,
        private readonly RentalImporter $rentalImporter,
    ) {}

    public function scrape(): array
    {
        $sources = Source::all();

        $captured = $this->sourceCapturer->capture($sources, $this->captureStore->capturePath());
        if (! $captured) {
            return [];
        }

        return collect($sources)
            ->flatMap(fn (Source $source) => $this->processSource($source))
            ->all();
    }

    /**
     * @return array new rentals of this source.
     */
    private function processSource(Source $source): array
    {
        $capture = $this->captureStore->find($source);
        if ($capture === null) {
            Log::error("❌ Capture not found for {$source->key}.");

            return [];
        }

        if ($this->captureStore->isUnchanged($source, $capture)) {
            Log::info("🔁 No changes for {$source->key}.");

            return [];
        }

        Log::info("📤 Sending {$source->key} to AI for analysis.");
        $extractedRentals = $this->listingExtractor->extract($source, $capture->html);

        $aiFailed = $extractedRentals === null;
        if ($aiFailed) {
            Log::warning("⚠️ AI failed for {$source->key}, snapshot not saved so it's retried next run.");

            return [];
        }

        $importResult = $this->rentalImporter->import($capture->withImageUrls($extractedRentals), $source);

        if ($importResult->hasFailures()) {
            Log::warning("⚠️ Some rentals failed for {$source->key}, snapshot not saved so it's retried next run.");

            return $importResult->newItems;
        }

        $this->captureStore->markProcessed($source, $capture);

        return $importResult->newItems;
    }
}
