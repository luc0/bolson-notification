<?php

namespace App\Providers;

use App\Contracts\RentalScraper;
use App\Services\BrowserRentalScraper;
use App\Services\FakeRentalScraper;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RentalScraper::class, function ($app) {
            $useFakeScraper = (bool) config('mock.scrape_mock');
            if ($useFakeScraper) {
                return $app->make(FakeRentalScraper::class);
            }

            return $app->make(BrowserRentalScraper::class);
        });
    }

    public function boot(): void
    {
        //
    }
}
