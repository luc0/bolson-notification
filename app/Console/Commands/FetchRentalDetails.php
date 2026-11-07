<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Rentals\Services\RentalDetailService;
use Illuminate\Console\Command;

class FetchRentalDetails extends Command
{
    protected $signature = 'rentals:fetch-details {--limit=200 : Max rentals to process}
        {--missing-titles : Re-process rentals that already have their detail but no AI title}';

    protected $description = 'Completes available rentals with the data of their detail page (area, bedrooms, services...)';

    public function handle(RentalDetailService $detailService): int
    {
        $enrichedCount = $detailService->enrichPending((int) $this->option('limit'), (bool) $this->option('missing-titles'));
        $this->info("Details completed for {$enrichedCount} rentals.");

        return self::SUCCESS;
    }
}
