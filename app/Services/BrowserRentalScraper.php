<?php

namespace App\Services;

use App\Contracts\RentalScraper;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Captures every source with Puppeteer (scripts/scrape.cjs), then extracts and stores the rentals
 * of the sites whose capture changed since the last successful run.
 */
class BrowserRentalScraper implements RentalScraper
{
    private const NODE_SCRAPER_TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly AiProcessingService $aiProcessingService,
        private readonly RentalService $rentalService,
    ) {
    }

    public function scrape(): array
    {
        $sources = Source::all();

        $captured = $this->captureSources($sources);
        if (!$captured) {
            return [];
        }

        return collect($sources)
            ->flatMap(fn (Source $source) => $this->processSource($source))
            ->all();
    }

    private function captureSources(array $sources): bool
    {
        File::ensureDirectoryExists(config('harvesting.capture_path'));

        $input = [
            'capturePath' => config('harvesting.capture_path'),
            'sites' => array_map(fn (Source $source) => $this->scraperInput($source), $sources),
        ];
        $result = Process::timeout(self::NODE_SCRAPER_TIMEOUT_SECONDS)
            ->input(json_encode($input))
            ->run(['node', base_path('scripts/scrape.cjs')]);

        if ($result->failed()) {
            Log::error('❌ Node scraper failed: ' . $result->errorOutput());
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
        if (!File::exists($this->capturePath($source))) {
            Log::error("❌ Capture not found for {$source->key}: {$this->capturePath($source)}");
            return [];
        }

        $html = File::get($this->capturePath($source));
        $images = $this->loadImages($source);

        // Images are part of the snapshot: an agency may only swap the photo for one stamped "ALQUILADO".
        $snapshot = $html . json_encode($images);
        $isUnchanged = File::exists($this->snapshotPath($source)) && File::get($this->snapshotPath($source)) === $snapshot;
        if ($isUnchanged) {
            Log::info("🔁 No changes for {$source->key}.");
            return [];
        }

        Log::info("📤 Sending {$source->key} to AI for analysis.");
        $extractedRentals = $this->aiProcessingService->process($source, $html);

        $aiFailed = $extractedRentals === null;
        if ($aiFailed) {
            Log::warning("⚠️ AI failed for {$source->key}, snapshot not saved so it's retried next run.");
            return [];
        }

        $storeResult = $this->rentalService->store($this->resolveImageUrls($extractedRentals, $images), $source->listingUrl);

        if ($storeResult->hasFailures()) {
            Log::warning("⚠️ Some rentals failed for {$source->key}, snapshot not saved so it's retried next run.");
            return $storeResult->newItems;
        }

        // Only save the snapshot once the site was fully processed.
        File::ensureDirectoryExists(config('harvesting.snapshot_path'));
        File::put($this->snapshotPath($source), $snapshot);

        return $storeResult->newItems;
    }

    /**
     * What scripts/scrape.cjs needs for each source.
     */
    private function scraperInput(Source $source): array
    {
        return ['key' => $source->key, 'url' => $source->listingUrl, 'waitFor' => $source->waitFor];
    }

    private function capturePath(Source $source): string
    {
        return config('harvesting.capture_path') . "/{$source->key}.html";
    }

    /**
     * scripts/scrape.cjs saves the real image URLs next to the capture (the HTML only has "#img-N" ids).
     */
    private function imagesPath(Source $source): string
    {
        return config('harvesting.capture_path') . "/{$source->key}.images.json";
    }

    private function snapshotPath(Source $source): string
    {
        return config('harvesting.snapshot_path') . "/{$source->key}.html";
    }

    /**
     * scripts/scrape.cjs replaces each <img src> with "#img-N" and saves the real URLs next to the capture.
     */
    private function loadImages(Source $source): array
    {
        if (!File::exists($this->imagesPath($source))) {
            return [];
        }

        return json_decode(File::get($this->imagesPath($source)), true) ?? [];
    }

    private function resolveImageUrls(array $rentals, array $images): array
    {
        return array_map(function ($rental) use ($images) {
            if (!is_array($rental)) {
                return $rental;
            }

            return [...$rental, 'Image' => $this->resolveImageUrl($rental['Image'] ?? null, $images)];
        }, $rentals);
    }

    private function resolveImageUrl(mixed $imageId, array $images): ?string
    {
        $isImageId = is_string($imageId) && preg_match('/^#img-(\d+)$/', trim($imageId), $matches) === 1;
        if (!$isImageId) {
            return null;
        }

        return $images[(int) $matches[1]] ?? null;
    }
}
