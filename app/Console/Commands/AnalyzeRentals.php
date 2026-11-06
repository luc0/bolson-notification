<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Contracts\RentalScraper;
use App\Domains\Harvesting\Services\RentalDetailService;
use App\Domains\Subscriptions\Services\NewRentalsNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeRentals extends Command
{
    protected $signature = 'rentals:analyze';

    protected $description = 'Scrapes the rental sources, completes details and notifies subscribers about new rentals';

    public function handle(
        RentalScraper $scraper,
        RentalDetailService $rentalDetailService,
        NewRentalsNotifier $newRentalsNotifier,
    ): int {
        Log::info('📦 rentals:analyze started.');

        $newRentals = $scraper->scrape();

        // Runs even when no site changed: completes new rentals and retries previous failures.
        $rentalDetailService->enrichPending();

        // Last run output, handy for debugging.
        Storage::put('rental.json', json_encode($newRentals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (empty($newRentals)) {
            Log::info('⚠️ No new rentals to notify.');

            return self::SUCCESS;
        }

        $newRentalsNotifier->notifyNewRentals(count($newRentals));

        return self::SUCCESS;
    }
}
