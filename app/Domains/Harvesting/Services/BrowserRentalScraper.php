<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Contracts\RentalScraper;
use App\Domains\Harvesting\Data\Capture;
use App\Domains\Harvesting\Support\CaptureStore;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Captures every source with Puppeteer (scripts/scrape.cjs), then extracts and imports the rentals
 * of the sources whose capture changed since the last successful run.
 */
class BrowserRentalScraper implements RentalScraper
{
    private const NODE_SCRAPER_TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly CaptureStore $captureStore,
        private readonly ListingExtractor $listingExtractor,
        private readonly RentalImporter $rentalImporter,
    ) {}

    public function scrape(): array
    {
        $sources = Source::all();

        $captured = $this->captureSources($sources);
        if (! $captured) {
            return [];
        }

        return collect($sources)
            ->flatMap(fn (Source $source) => $this->processSource($source))
            ->all();
    }

    /**
     * @param  Source[]  $sources
     */
    private function captureSources(array $sources): bool
    {
        File::ensureDirectoryExists($this->captureStore->capturePath());

        $input = [
            'capturePath' => $this->captureStore->capturePath(),
            'sites' => array_map(
                fn (Source $source) => ['key' => $source->key, 'url' => $source->listingUrl, 'waitFor' => $source->waitFor],
                $sources,
            ),
        ];
        $result = Process::timeout(self::NODE_SCRAPER_TIMEOUT_SECONDS)
            ->input(json_encode($input))
            ->run(['node', base_path('scripts/scrape.cjs')]);

        if ($result->failed()) {
            Log::error('❌ Node scraper failed: '.$result->errorOutput());

            return false;
        }

        Log::info('✅ Sources captured.');

        return true;
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

        $importResult = $this->rentalImporter->import($this->withImageUrls($extractedRentals, $capture), $source->listingUrl);

        if ($importResult->hasFailures()) {
            Log::warning("⚠️ Some rentals failed for {$source->key}, snapshot not saved so it's retried next run.");

            return $importResult->newItems;
        }

        $this->captureStore->markProcessed($source, $capture);

        return $importResult->newItems;
    }

    /**
     * The AI returns the "#img-N" id of the main photo: replace it with the real URL.
     */
    private function withImageUrls(array $rentals, Capture $capture): array
    {
        return array_map(function ($rental) use ($capture) {
            if (! is_array($rental)) {
                return $rental;
            }

            return [...$rental, 'Image' => $capture->imageUrl($rental['Image'] ?? null)];
        }, $rentals);
    }
}
