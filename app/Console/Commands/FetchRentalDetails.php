<?php

namespace App\Console\Commands;

use App\Services\RentalDetailService;
use Illuminate\Console\Command;

class FetchRentalDetails extends Command
{
    protected $signature = 'rentals:fetch-details {--limit=200 : Max rentals to process}';
    protected $description = 'Completes available rentals with the data of their detail page (area, bedrooms, services...)';

    public function handle(RentalDetailService $detailService): int
    {
        $enrichedCount = $detailService->enrichPending((int) $this->option('limit'));
        $this->info("Details completed for {$enrichedCount} rentals.");

        return self::SUCCESS;
    }
}
