<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Opens each source's listing page with Puppeteer (scripts/scrape.cjs) and saves a cleaned capture.
 */
class SourceCapturer
{
    private const NODE_SCRAPER_TIMEOUT_SECONDS = 120;

    /**
     * @param  Source[]  $sources
     */
    public function capture(array $sources, string $capturePath): bool
    {
        File::ensureDirectoryExists($capturePath);

        $input = [
            'capturePath' => $capturePath,
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
}
