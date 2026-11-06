<?php

namespace App\Console\Commands;

use App\Contracts\RentalScraper;
use App\Services\NotificationService;
use App\Services\RentalDetailService;
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
        NotificationService $notificationService,
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

        $notificationService->sendNewRentalsNotification(count($newRentals));

        return self::SUCCESS;
    }
}
