<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Services\BrowserLandScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnalyzeLands extends Command
{
    protected $signature = 'lands:analyze';

    protected $description = 'Scrapes the land sources and stores the lands for sale (no notifications)';

    public function handle(BrowserLandScraper $scraper): int
    {
        Log::info('📦 lands:analyze started.');

        $newLands = $scraper->scrape();

        $this->info(count($newLands).' new lands.');

        return self::SUCCESS;
    }
}
