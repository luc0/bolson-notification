<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Services\BrowserHouseScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnalyzeHouses extends Command
{
    protected $signature = 'houses:analyze';

    protected $description = 'Scrapes the house sources and stores the houses for sale (no notifications)';

    public function handle(BrowserHouseScraper $scraper): int
    {
        Log::info('📦 houses:analyze started.');

        $newHouses = $scraper->scrape();

        $this->info(count($newHouses).' new houses.');

        return self::SUCCESS;
    }
}
